--
-- PostgreSQL database dump
--

-- Dumped from database version 17.5 (Ubuntu 17.5-1.pgdg20.04+1)
-- Dumped by pg_dump version 17.5 (Ubuntu 17.5-1.pgdg20.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA public;


--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA public IS 'standard public schema';


--
-- Name: alertas_validar_transicion_estado(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.alertas_validar_transicion_estado() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.estado IS NOT DISTINCT FROM OLD.estado THEN
        RETURN NEW;
    END IF;

    IF (OLD.estado = 'enviada' AND NEW.estado IN ('respondida', 'sin_respuesta'))
        OR (OLD.estado = 'respondida' AND NEW.estado IN ('completada', 'sin_respuesta')) THEN
        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'alertas.estado no admite el cambio de % a %', OLD.estado, NEW.estado
        USING ERRCODE = 'check_violation';
END;
$$;


--
-- Name: auditoria_eventos_solo_insercion(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.auditoria_eventos_solo_insercion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    RAISE EXCEPTION 'auditoria_eventos is insert-only'
        USING ERRCODE = 'restrict_violation';
END;
$$;


--
-- Name: avisos_no_se_borran(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.avisos_no_se_borran() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    RAISE EXCEPTION 'avisos are never deleted; they only change state'
        USING ERRCODE = 'restrict_violation';
END;
$$;


--
-- Name: avisos_solo_decision_editable(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.avisos_solo_decision_editable() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    permitidas text[] := ARRAY['estado', 'decidido_por', 'decidido_en', 'motivo_descarte', 'updated_at'];
BEGIN
    IF (to_jsonb(NEW) - permitidas) IS DISTINCT FROM (to_jsonb(OLD) - permitidas) THEN
        RAISE EXCEPTION 'avisos only admit changes to estado and the decision fields'
            USING ERRCODE = 'check_violation';
    END IF;

    IF OLD.estado <> 'recibido' AND (
        NEW.estado IS DISTINCT FROM OLD.estado
        OR NEW.decidido_por IS DISTINCT FROM OLD.decidido_por
        OR NEW.decidido_en IS DISTINCT FROM OLD.decidido_en
        OR NEW.motivo_descarte IS DISTINCT FROM OLD.motivo_descarte
    ) THEN
        RAISE EXCEPTION 'A decided aviso is terminal and its decision cannot change'
            USING ERRCODE = 'check_violation';
    END IF;

    IF (OLD.decidido_por IS NOT NULL AND NEW.decidido_por IS DISTINCT FROM OLD.decidido_por)
        OR (OLD.decidido_en IS NOT NULL AND NEW.decidido_en IS DISTINCT FROM OLD.decidido_en)
        OR (OLD.motivo_descarte IS NOT NULL AND NEW.motivo_descarte IS DISTINCT FROM OLD.motivo_descarte) THEN
        RAISE EXCEPTION 'A recorded decision cannot be rewritten'
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: casos_alta_requiere_informe_aprobado(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.casos_alta_requiere_informe_aprobado() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.estado = 'alta' THEN
        PERFORM 1 FROM informes_resolucion WHERE caso_id = NEW.id AND estado = 'aprobado' FOR SHARE;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'A caso cannot reach alta without an approved informe_resolucion'
                USING ERRCODE = 'check_violation';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: casos_numero_desde_contador(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.casos_numero_desde_contador() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    emitido integer;
BEGIN
    IF NEW.numero IS NULL THEN
        NEW.numero := siguiente_numero_caso(NEW.establecimiento_id, NEW.anio_escolar_id);
    ELSE
        SELECT ultimo_numero INTO emitido FROM caso_contadores
        WHERE establecimiento_id = NEW.establecimiento_id AND anio_escolar_id = NEW.anio_escolar_id;

        IF emitido IS NULL OR NEW.numero < 1 OR NEW.numero > emitido THEN
            RAISE EXCEPTION 'casos.numero must come from the counter'
                USING ERRCODE = 'check_violation';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: casos_numero_inmutable(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.casos_numero_inmutable() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.numero IS DISTINCT FROM OLD.numero
        OR NEW.anio_escolar_id IS DISTINCT FROM OLD.anio_escolar_id
        OR NEW.establecimiento_id IS DISTINCT FROM OLD.establecimiento_id THEN
        RAISE EXCEPTION 'The number, anio escolar and establecimiento of a caso cannot change'
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: derivaciones_origen_coincide_con_aviso(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.derivaciones_origen_coincide_con_aviso() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    aviso_origen text;
BEGIN
    IF NEW.aviso_id IS NOT NULL THEN
        SELECT origen INTO aviso_origen FROM avisos WHERE id = NEW.aviso_id;

        IF (NEW.origen = 'aviso_funcionario' AND aviso_origen IS DISTINCT FROM 'funcionario')
            OR (NEW.origen = 'apoderado_presencial' AND aviso_origen IS DISTINCT FROM 'apoderado_presencial') THEN
            RAISE EXCEPTION 'derivaciones.origen must match avisos.origen'
                USING ERRCODE = 'check_violation';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: establecimiento_id_inmutable(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.establecimiento_id_inmutable() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.establecimiento_id IS DISTINCT FROM OLD.establecimiento_id THEN
        RAISE EXCEPTION 'establecimiento_id of % cannot change', TG_TABLE_NAME
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: exigir_padre_plantilla(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.exigir_padre_plantilla() RETURNS trigger
    LANGUAGE plpgsql
    AS $_$
DECLARE
    parent_table text := TG_ARGV[0];
    parent_column text := TG_ARGV[1];
    child_value bigint;
    parent_establecimiento bigint;
BEGIN
    IF NEW.establecimiento_id IS NOT NULL THEN
        RETURN NEW;
    END IF;

    EXECUTE format('SELECT ($1).%I', parent_column) INTO child_value USING NEW;
    EXECUTE format('SELECT establecimiento_id FROM %I WHERE id = $1', parent_table)
        INTO parent_establecimiento USING child_value;

    IF parent_establecimiento IS NOT NULL THEN
        RAISE EXCEPTION 'A base template row cannot belong to an establecimiento-owned parent'
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$_$;


--
-- Name: informes_aprobado_mientras_caso_en_alta(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.informes_aprobado_mientras_caso_en_alta() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        PERFORM 1 FROM casos WHERE id = OLD.caso_id AND estado = 'alta' FOR SHARE;

        IF FOUND THEN
            RAISE EXCEPTION 'The informe_resolucion of a caso in alta cannot be deleted'
                USING ERRCODE = 'restrict_violation';
        END IF;

        RETURN OLD;
    END IF;

    IF NEW.estado <> 'aprobado' OR NEW.caso_id <> OLD.caso_id THEN
        PERFORM 1 FROM casos WHERE id = OLD.caso_id AND estado = 'alta' FOR SHARE;

        IF FOUND THEN
            RAISE EXCEPTION 'The informe_resolucion of a caso in alta must stay approved'
                USING ERRCODE = 'check_violation';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: membresias_soporte_requiere_bandera(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.membresias_soporte_requiere_bandera() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.establecimiento_id IS NULL THEN
        PERFORM 1 FROM usuarios WHERE id = NEW.usuario_id AND es_soporte_vinkured FOR SHARE;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'Support membership requires a usuario with es_soporte_vinkured'
                USING ERRCODE = 'check_violation';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: protocolos_basado_en_plantilla(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.protocolos_basado_en_plantilla() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.basado_en_id IS NOT NULL AND EXISTS (
        SELECT 1 FROM protocolos WHERE id = NEW.basado_en_id AND establecimiento_id IS NOT NULL
    ) THEN
        RAISE EXCEPTION 'basado_en_id must reference a base template protocol'
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: siguiente_numero_caso(bigint, bigint); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.siguiente_numero_caso(p_establecimiento_id bigint, p_anio_escolar_id bigint) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    numero integer;
BEGIN
    INSERT INTO caso_contadores (establecimiento_id, anio_escolar_id, ultimo_numero, created_at, updated_at)
    VALUES (p_establecimiento_id, p_anio_escolar_id, 1, now(), now())
    ON CONFLICT (establecimiento_id, anio_escolar_id)
    DO UPDATE SET ultimo_numero = caso_contadores.ultimo_numero + 1, updated_at = now()
    RETURNING ultimo_numero INTO numero;

    RETURN numero;
END;
$$;


--
-- Name: usuarios_bandera_soporte_con_membresia(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.usuarios_bandera_soporte_con_membresia() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.es_soporte_vinkured AND NOT NEW.es_soporte_vinkured AND EXISTS (
        SELECT 1 FROM membresias WHERE usuario_id = NEW.id AND establecimiento_id IS NULL
    ) THEN
        RAISE EXCEPTION 'Cannot remove es_soporte_vinkured while a support membership exists'
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: accion_responsables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.accion_responsables (
    id bigint NOT NULL,
    establecimiento_id bigint,
    accion_id bigint NOT NULL,
    responsable character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT accion_responsables_responsable_check CHECK (((responsable)::text = ANY (ARRAY[('profesor_jefe'::character varying)::text, ('docente_asignatura'::character varying)::text, ('coordinador_convivencia'::character varying)::text, ('directivo'::character varying)::text, ('orientador'::character varying)::text, ('profesional_apoyo'::character varying)::text])))
);


--
-- Name: accion_responsables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.accion_responsables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: accion_responsables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.accion_responsables_id_seq OWNED BY public.accion_responsables.id;


--
-- Name: acciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.acciones (
    id bigint NOT NULL,
    establecimiento_id bigint,
    procedimiento_id bigint NOT NULL,
    orden smallint NOT NULL,
    titulo character varying(255) NOT NULL,
    descripcion text,
    medidas_remediales text,
    plazo_dias smallint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT acciones_orden_check CHECK ((orden >= 0)),
    CONSTRAINT acciones_plazo_dias_check CHECK (((plazo_dias IS NULL) OR (plazo_dias > 0)))
);


--
-- Name: acciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.acciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: acciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.acciones_id_seq OWNED BY public.acciones.id;


--
-- Name: actas_entrevista; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.actas_entrevista (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    caso_id bigint NOT NULL,
    seguimiento_id bigint,
    persona_id bigint NOT NULL,
    rol character varying(255) NOT NULL,
    curso_o_cargo character varying(255) NOT NULL,
    entrevistador_id bigint NOT NULL,
    realizada_en timestamp(0) without time zone NOT NULL,
    lugar character varying(255) NOT NULL,
    acompanante_nombre character varying(255),
    acompanante_relacion character varying(255),
    informado_confidencialidad boolean DEFAULT false NOT NULL,
    relato text,
    preguntas_respuestas text,
    observaciones text,
    compromisos text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT actas_entrevista_rol_check CHECK (((rol)::text = ANY (ARRAY[('afectado'::character varying)::text, ('involucrado'::character varying)::text, ('informante'::character varying)::text, ('testigo'::character varying)::text])))
);


--
-- Name: actas_entrevista_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.actas_entrevista_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: actas_entrevista_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.actas_entrevista_id_seq OWNED BY public.actas_entrevista.id;


--
-- Name: adjuntos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adjuntos (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    adjuntable_tipo character varying(255) NOT NULL,
    adjuntable_id bigint NOT NULL,
    nombre_original character varying(255) NOT NULL,
    ruta character varying(255) NOT NULL,
    mime character varying(255) NOT NULL,
    tamano bigint NOT NULL,
    subido_por bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT adjuntos_adjuntable_tipo_check CHECK (((adjuntable_tipo)::text = ANY (ARRAY[('alerta'::character varying)::text, ('derivacion'::character varying)::text, ('seguimiento'::character varying)::text, ('protocolo'::character varying)::text]))),
    CONSTRAINT adjuntos_tamano_check CHECK ((tamano > 0))
);


--
-- Name: adjuntos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adjuntos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adjuntos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adjuntos_id_seq OWNED BY public.adjuntos.id;


--
-- Name: alerta_respuestas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.alerta_respuestas (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    alerta_id bigint NOT NULL,
    funcionario_id bigint NOT NULL,
    texto text NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    protocolo_rectificado boolean DEFAULT false NOT NULL,
    protocolo_anterior_id bigint,
    rol_al_responder character varying(255),
    CONSTRAINT alerta_respuestas_protocolo_anterior_check CHECK (((protocolo_anterior_id IS NULL) OR protocolo_rectificado)),
    CONSTRAINT alerta_respuestas_rol_al_responder_check CHECK (((rol_al_responder IS NULL) OR ((rol_al_responder)::text = ANY (ARRAY[('profesor_jefe'::character varying)::text, ('coordinador'::character varying)::text]))))
);


--
-- Name: alerta_respuestas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.alerta_respuestas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: alerta_respuestas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.alerta_respuestas_id_seq OWNED BY public.alerta_respuestas.id;


--
-- Name: alertas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.alertas (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    apoderado_id bigint NOT NULL,
    estudiante_id bigint NOT NULL,
    protocolo_id bigint,
    destinatario character varying(255) NOT NULL,
    descripcion text NOT NULL,
    estado character varying(255) DEFAULT 'enviada'::character varying NOT NULL,
    enviada_en timestamp(0) without time zone NOT NULL,
    vence_docente_en timestamp(0) without time zone NOT NULL,
    escalada_en timestamp(0) without time zone,
    vence_coordinador_en timestamp(0) without time zone NOT NULL,
    respondida_en timestamp(0) without time zone,
    completada_en timestamp(0) without time zone,
    sin_respuesta_en timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT alertas_completada_en_check CHECK ((((estado)::text <> 'completada'::text) OR (completada_en IS NOT NULL))),
    CONSTRAINT alertas_estado_check CHECK (((estado)::text = ANY (ARRAY[('enviada'::character varying)::text, ('respondida'::character varying)::text, ('completada'::character varying)::text, ('sin_respuesta'::character varying)::text]))),
    CONSTRAINT alertas_respondida_en_check CHECK ((((estado)::text <> ALL (ARRAY[('respondida'::character varying)::text, ('completada'::character varying)::text])) OR (respondida_en IS NOT NULL))),
    CONSTRAINT alertas_sin_respuesta_en_check CHECK ((((estado)::text <> 'sin_respuesta'::text) OR (sin_respuesta_en IS NOT NULL)))
);


--
-- Name: alertas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.alertas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: alertas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.alertas_id_seq OWNED BY public.alertas.id;


--
-- Name: anios_escolares; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.anios_escolares (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    anio smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT anios_escolares_anio_check CHECK (((anio >= 2000) AND (anio <= 2100)))
);


--
-- Name: anios_escolares_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.anios_escolares_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: anios_escolares_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.anios_escolares_id_seq OWNED BY public.anios_escolares.id;


--
-- Name: apoderados; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.apoderados (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    persona_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: apoderados_estudiantes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.apoderados_estudiantes (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    apoderado_id bigint NOT NULL,
    estudiante_id bigint NOT NULL,
    es_titular boolean DEFAULT false NOT NULL,
    parentesco character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: apoderados_estudiantes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.apoderados_estudiantes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: apoderados_estudiantes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.apoderados_estudiantes_id_seq OWNED BY public.apoderados_estudiantes.id;


--
-- Name: apoderados_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.apoderados_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: apoderados_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.apoderados_id_seq OWNED BY public.apoderados.id;


--
-- Name: asignaciones_docentes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asignaciones_docentes (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    funcionario_id bigint NOT NULL,
    curso_id bigint NOT NULL,
    asignatura_id bigint,
    es_jefatura boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT asignaciones_docentes_jefatura_o_asignatura_check CHECK (((es_jefatura AND (asignatura_id IS NULL)) OR ((NOT es_jefatura) AND (asignatura_id IS NOT NULL))))
);


--
-- Name: asignaciones_docentes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asignaciones_docentes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asignaciones_docentes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asignaciones_docentes_id_seq OWNED BY public.asignaciones_docentes.id;


--
-- Name: asignaturas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asignaturas (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: asignaturas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asignaturas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asignaturas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asignaturas_id_seq OWNED BY public.asignaturas.id;


--
-- Name: auditoria_eventos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.auditoria_eventos (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    usuario_id bigint NOT NULL,
    accion character varying(255) NOT NULL,
    entidad_tipo character varying(255) NOT NULL,
    entidad_id bigint NOT NULL,
    detalle json,
    ip character varying(45),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT auditoria_eventos_accion_check CHECK (((accion)::text = ANY (ARRAY[('crear'::character varying)::text, ('cambiar_estado'::character varying)::text, ('ver'::character varying)::text, ('descargar'::character varying)::text, ('exportar'::character varying)::text])))
);


--
-- Name: auditoria_eventos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.auditoria_eventos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: auditoria_eventos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.auditoria_eventos_id_seq OWNED BY public.auditoria_eventos.id;


--
-- Name: avisos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.avisos (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    origen character varying(255) NOT NULL,
    descripcion text NOT NULL,
    estado character varying(255) DEFAULT 'recibido'::character varying NOT NULL,
    recibido_por bigint NOT NULL,
    recibido_en timestamp(0) without time zone NOT NULL,
    informante_persona_id bigint,
    informante_nombre character varying(255),
    informante_contacto character varying(255),
    decidido_por bigint,
    decidido_en timestamp(0) without time zone,
    motivo_descarte text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT avisos_estado_check CHECK (((estado)::text = ANY (ARRAY[('recibido'::character varying)::text, ('formalizado'::character varying)::text, ('descartado'::character varying)::text]))),
    CONSTRAINT avisos_motivo_descarte_check CHECK ((((estado)::text <> 'descartado'::text) OR (motivo_descarte IS NOT NULL))),
    CONSTRAINT avisos_origen_check CHECK (((origen)::text = ANY (ARRAY[('funcionario'::character varying)::text, ('apoderado_presencial'::character varying)::text])))
);


--
-- Name: avisos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.avisos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: avisos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.avisos_id_seq OWNED BY public.avisos.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cambios_documento; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cambios_documento (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    persona_id bigint NOT NULL,
    tipo_anterior character varying(255) NOT NULL,
    numero_anterior character varying(255) NOT NULL,
    tipo_nuevo character varying(255) NOT NULL,
    numero_nuevo character varying(255) NOT NULL,
    registrado_por bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT cambios_documento_tipo_anterior_check CHECK (((tipo_anterior)::text = ANY (ARRAY[('rut'::character varying)::text, ('ipe'::character varying)::text, ('pasaporte'::character varying)::text, ('otro'::character varying)::text]))),
    CONSTRAINT cambios_documento_tipo_nuevo_check CHECK (((tipo_nuevo)::text = ANY (ARRAY[('rut'::character varying)::text, ('ipe'::character varying)::text, ('pasaporte'::character varying)::text, ('otro'::character varying)::text])))
);


--
-- Name: cambios_documento_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cambios_documento_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cambios_documento_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cambios_documento_id_seq OWNED BY public.cambios_documento.id;


--
-- Name: caso_contadores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.caso_contadores (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    anio_escolar_id bigint NOT NULL,
    ultimo_numero integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT caso_contadores_ultimo_numero_check CHECK ((ultimo_numero >= 0))
);


--
-- Name: caso_contadores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.caso_contadores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: caso_contadores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.caso_contadores_id_seq OWNED BY public.caso_contadores.id;


--
-- Name: caso_partes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.caso_partes (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    caso_id bigint NOT NULL,
    persona_id bigint NOT NULL,
    rol character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT caso_partes_rol_check CHECK (((rol)::text = ANY (ARRAY[('afectado'::character varying)::text, ('involucrado'::character varying)::text, ('informante'::character varying)::text, ('testigo'::character varying)::text])))
);


--
-- Name: caso_partes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.caso_partes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: caso_partes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.caso_partes_id_seq OWNED BY public.caso_partes.id;


--
-- Name: casos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.casos (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    anio_escolar_id bigint NOT NULL,
    numero integer NOT NULL,
    resumen text NOT NULL,
    estado character varying(255) DEFAULT 'abierto'::character varying NOT NULL,
    abierto_por bigint NOT NULL,
    abierto_en timestamp(0) without time zone NOT NULL,
    alta_en timestamp(0) without time zone,
    alta_por bigint,
    observacion_alta text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT casos_alta_check CHECK ((((estado)::text <> 'alta'::text) OR ((alta_en IS NOT NULL) AND (alta_por IS NOT NULL)))),
    CONSTRAINT casos_estado_check CHECK (((estado)::text = ANY (ARRAY[('abierto'::character varying)::text, ('en_seguimiento'::character varying)::text, ('alta'::character varying)::text]))),
    CONSTRAINT casos_numero_check CHECK ((numero > 0))
);


--
-- Name: casos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.casos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: casos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.casos_id_seq OWNED BY public.casos.id;


--
-- Name: comunas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.comunas (
    id bigint NOT NULL,
    region_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    codigo character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: comunas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.comunas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: comunas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.comunas_id_seq OWNED BY public.comunas.id;


--
-- Name: configuraciones_establecimiento; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuraciones_establecimiento (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    plazo_profesor_jefe_dias smallint NOT NULL,
    plazo_coordinador_dias smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT configuraciones_establecimiento_plazo_coordinador_dias_check CHECK ((plazo_coordinador_dias > 0)),
    CONSTRAINT configuraciones_establecimiento_plazo_profesor_jefe_dias_check CHECK ((plazo_profesor_jefe_dias > 0))
);


--
-- Name: configuraciones_establecimiento_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuraciones_establecimiento_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuraciones_establecimiento_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuraciones_establecimiento_id_seq OWNED BY public.configuraciones_establecimiento.id;


--
-- Name: cursos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cursos (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    anio_escolar_id bigint NOT NULL,
    grado_id bigint NOT NULL,
    letra character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cursos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cursos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cursos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cursos_id_seq OWNED BY public.cursos.id;


--
-- Name: derivacion_externas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.derivacion_externas (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    derivacion_id bigint NOT NULL,
    destino character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT derivacion_externas_destino_check CHECK (((destino)::text = ANY (ARRAY[('salud'::character varying)::text, ('opd'::character varying)::text, ('policia_fiscalia'::character varying)::text, ('tribunal_familia'::character varying)::text])))
);


--
-- Name: derivacion_externas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.derivacion_externas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: derivacion_externas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.derivacion_externas_id_seq OWNED BY public.derivacion_externas.id;


--
-- Name: derivaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.derivaciones (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    caso_id bigint NOT NULL,
    origen character varying(255) NOT NULL,
    alerta_id bigint,
    aviso_id bigint,
    emisor_tipo character varying(255) NOT NULL,
    derivado_por bigint NOT NULL,
    derivado_a bigint,
    protocolo_id bigint,
    descripcion text NOT NULL,
    canal_recepcion character varying(255) NOT NULL,
    recibido_por bigint NOT NULL,
    recibido_en timestamp(0) without time zone NOT NULL,
    informante_nombre character varying(255),
    informante_contacto character varying(255),
    informante_relacion character varying(255) NOT NULL,
    tipo_situacion character varying(255) NOT NULL,
    tipo_situacion_otro character varying(255),
    medidas_resguardo text,
    apoderados_informados boolean NOT NULL,
    apoderados_informados_en timestamp(0) without time zone,
    apoderados_no_informados_motivo text,
    protocolo_activado_en timestamp(0) without time zone,
    protocolo_responsable_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT derivaciones_apoderados_informados_en_check CHECK (((apoderados_informados_en IS NULL) OR apoderados_informados)),
    CONSTRAINT derivaciones_apoderados_no_informados_check CHECK ((apoderados_informados OR (apoderados_no_informados_motivo IS NOT NULL))),
    CONSTRAINT derivaciones_canal_recepcion_check CHECK (((canal_recepcion)::text = ANY (ARRAY[('presencial'::character varying)::text, ('formulario_vinkured'::character varying)::text, ('correo'::character varying)::text, ('otro'::character varying)::text]))),
    CONSTRAINT derivaciones_informante_relacion_check CHECK (((informante_relacion)::text = ANY (ARRAY[('estudiante'::character varying)::text, ('apoderado'::character varying)::text, ('docente'::character varying)::text, ('asistente'::character varying)::text, ('directivo'::character varying)::text, ('otro'::character varying)::text]))),
    CONSTRAINT derivaciones_origen_check CHECK (((origen)::text = ANY (ARRAY[('alerta'::character varying)::text, ('aviso_funcionario'::character varying)::text, ('apoderado_presencial'::character varying)::text]))),
    CONSTRAINT derivaciones_origen_coherente_check CHECK (((((origen)::text = 'alerta'::text) AND (alerta_id IS NOT NULL) AND (aviso_id IS NULL)) OR (((origen)::text = ANY (ARRAY[('aviso_funcionario'::character varying)::text, ('apoderado_presencial'::character varying)::text])) AND (aviso_id IS NOT NULL) AND (alerta_id IS NULL)))),
    CONSTRAINT derivaciones_tipo_situacion_check CHECK (((tipo_situacion)::text = ANY (ARRAY[('acoso_violencia_escolar'::character varying)::text, ('vulneracion_derechos'::character varying)::text, ('agresion_connotacion_sexual'::character varying)::text, ('accidente'::character varying)::text, ('drogas_alcohol'::character varying)::text, ('otra'::character varying)::text]))),
    CONSTRAINT derivaciones_tipo_situacion_otro_check CHECK ((((tipo_situacion)::text = 'otra'::text) = (tipo_situacion_otro IS NOT NULL)))
);


--
-- Name: derivaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.derivaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: derivaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.derivaciones_id_seq OWNED BY public.derivaciones.id;


--
-- Name: dias_no_habiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dias_no_habiles (
    id bigint NOT NULL,
    establecimiento_id bigint,
    fecha date NOT NULL,
    descripcion character varying(255),
    origen character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT dias_no_habiles_origen_check CHECK (((origen)::text = ANY (ARRAY[('oficial'::character varying)::text, ('establecimiento'::character varying)::text]))),
    CONSTRAINT dias_no_habiles_origen_establecimiento_check CHECK (((((origen)::text = 'oficial'::text) AND (establecimiento_id IS NULL)) OR (((origen)::text = 'establecimiento'::text) AND (establecimiento_id IS NOT NULL))))
);


--
-- Name: dias_no_habiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dias_no_habiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dias_no_habiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dias_no_habiles_id_seq OWNED BY public.dias_no_habiles.id;


--
-- Name: establecimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.establecimientos (
    id bigint NOT NULL,
    sostenedor_id bigint,
    rbd integer NOT NULL,
    dv_rbd character varying(1) NOT NULL,
    nombre character varying(255) NOT NULL,
    direccion character varying(255),
    comuna_id bigint NOT NULL,
    telefono character varying(255),
    correo_institucional character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT establecimientos_rbd_check CHECK ((rbd > 0))
);


--
-- Name: establecimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.establecimientos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: establecimientos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.establecimientos_id_seq OWNED BY public.establecimientos.id;


--
-- Name: estudiantes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.estudiantes (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    persona_id bigint NOT NULL,
    fecha_ingreso date,
    fecha_retiro date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT estudiantes_fechas_check CHECK (((fecha_retiro IS NULL) OR (fecha_ingreso IS NULL) OR (fecha_retiro >= fecha_ingreso)))
);


--
-- Name: estudiantes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.estudiantes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: estudiantes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.estudiantes_id_seq OWNED BY public.estudiantes.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: funcionario_funciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.funcionario_funciones (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    funcionario_id bigint NOT NULL,
    funcion_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: funcionario_funciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.funcionario_funciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: funcionario_funciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.funcionario_funciones_id_seq OWNED BY public.funcionario_funciones.id;


--
-- Name: funcionarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.funcionarios (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    persona_id bigint NOT NULL,
    fecha_ingreso date,
    fecha_termino date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT funcionarios_fechas_check CHECK (((fecha_termino IS NULL) OR (fecha_ingreso IS NULL) OR (fecha_termino >= fecha_ingreso)))
);


--
-- Name: funcionarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.funcionarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: funcionarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.funcionarios_id_seq OWNED BY public.funcionarios.id;


--
-- Name: funciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.funciones (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    categoria character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    clave character varying(60),
    CONSTRAINT funciones_categoria_check CHECK (((categoria)::text = ANY (ARRAY[('directivo'::character varying)::text, ('docente'::character varying)::text, ('asistente'::character varying)::text, ('profesional_apoyo'::character varying)::text]))),
    CONSTRAINT funciones_clave_check CHECK (((clave)::text ~ '^[a-z][a-z0-9_]*$'::text))
);


--
-- Name: funciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.funciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: funciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.funciones_id_seq OWNED BY public.funciones.id;


--
-- Name: grados; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grados (
    id bigint NOT NULL,
    nivel_ensenanza_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    orden smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT grados_orden_check CHECK ((orden >= 0))
);


--
-- Name: grados_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.grados_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grados_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.grados_id_seq OWNED BY public.grados.id;


--
-- Name: importaciones_usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.importaciones_usuarios (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    membresia_id bigint NOT NULL,
    archivo character varying(255) NOT NULL,
    estado character varying(255) NOT NULL,
    total integer DEFAULT 0 NOT NULL,
    creados integer DEFAULT 0 NOT NULL,
    con_error integer DEFAULT 0 NOT NULL,
    errores json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT importaciones_usuarios_contadores_check CHECK (((total >= 0) AND (creados >= 0) AND (con_error >= 0)))
);


--
-- Name: importaciones_usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.importaciones_usuarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: importaciones_usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.importaciones_usuarios_id_seq OWNED BY public.importaciones_usuarios.id;


--
-- Name: informe_diligencias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.informe_diligencias (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    informe_id bigint NOT NULL,
    fecha date NOT NULL,
    diligencia text NOT NULL,
    resultado text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: informe_diligencias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.informe_diligencias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: informe_diligencias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.informe_diligencias_id_seq OWNED BY public.informe_diligencias.id;


--
-- Name: informe_medidas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.informe_medidas (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    informe_id bigint NOT NULL,
    tipo character varying(255) NOT NULL,
    descripcion text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT informe_medidas_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('proteccion'::character varying)::text, ('apoyo'::character varying)::text, ('formativa'::character varying)::text, ('disciplinaria'::character varying)::text])))
);


--
-- Name: informe_medidas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.informe_medidas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: informe_medidas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.informe_medidas_id_seq OWNED BY public.informe_medidas.id;


--
-- Name: informes_resolucion; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.informes_resolucion (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    caso_id bigint NOT NULL,
    encargado_id bigint NOT NULL,
    fecha_informe date NOT NULL,
    inicio_en timestamp(0) without time zone,
    cierre_en timestamp(0) without time zone,
    resumen_hechos text,
    resultado character varying(255),
    fundamento text,
    estado character varying(255) DEFAULT 'borrador'::character varying NOT NULL,
    aprobado_por bigint,
    aprobado_en timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT informes_resolucion_aprobacion_check CHECK ((((estado)::text <> 'aprobado'::text) OR ((aprobado_por IS NOT NULL) AND (aprobado_en IS NOT NULL)))),
    CONSTRAINT informes_resolucion_estado_check CHECK (((estado)::text = ANY (ARRAY[('borrador'::character varying)::text, ('pendiente_aprobacion'::character varying)::text, ('aprobado'::character varying)::text]))),
    CONSTRAINT informes_resolucion_plazo_check CHECK (((inicio_en IS NULL) OR (cierre_en IS NULL) OR (cierre_en >= inicio_en))),
    CONSTRAINT informes_resolucion_resultado_check CHECK (((resultado IS NULL) OR ((resultado)::text = ANY (ARRAY[('acreditados'::character varying)::text, ('parcial'::character varying)::text, ('no_acreditados'::character varying)::text]))))
);


--
-- Name: informes_resolucion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.informes_resolucion_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: informes_resolucion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.informes_resolucion_id_seq OWNED BY public.informes_resolucion.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: matriculas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.matriculas (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    estudiante_id bigint NOT NULL,
    curso_id bigint NOT NULL,
    fecha_matricula date NOT NULL,
    fecha_retiro date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT matriculas_fechas_check CHECK (((fecha_retiro IS NULL) OR (fecha_retiro >= fecha_matricula)))
);


--
-- Name: matriculas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.matriculas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matriculas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.matriculas_id_seq OWNED BY public.matriculas.id;


--
-- Name: membresias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.membresias (
    id bigint NOT NULL,
    usuario_id bigint NOT NULL,
    establecimiento_id bigint,
    persona_id bigint,
    es_administrador boolean DEFAULT false NOT NULL,
    estado character varying(255) DEFAULT 'invitada'::character varying NOT NULL,
    invitada_en timestamp(0) without time zone,
    activada_en timestamp(0) without time zone,
    invitacion_hash character varying(255),
    invitacion_expira_en timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT membresias_establecimiento_persona_check CHECK (((establecimiento_id IS NULL) = (persona_id IS NULL))),
    CONSTRAINT membresias_estado_check CHECK (((estado)::text = ANY (ARRAY[('invitada'::character varying)::text, ('activa'::character varying)::text, ('suspendida'::character varying)::text])))
);


--
-- Name: membresias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.membresias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: membresias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.membresias_id_seq OWNED BY public.membresias.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: niveles_ensenanza; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.niveles_ensenanza (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    orden smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT niveles_ensenanza_orden_check CHECK ((orden >= 0))
);


--
-- Name: niveles_ensenanza_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.niveles_ensenanza_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: niveles_ensenanza_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.niveles_ensenanza_id_seq OWNED BY public.niveles_ensenanza.id;


--
-- Name: notificaciones_apoderado; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notificaciones_apoderado (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    caso_id bigint NOT NULL,
    apoderado_id bigint NOT NULL,
    estudiante_id bigint NOT NULL,
    motivo character varying(255) NOT NULL,
    contenido text NOT NULL,
    citacion_en timestamp(0) without time zone,
    citacion_lugar character varying(255),
    plazo_apelacion_dias smallint,
    enviada_en timestamp(0) without time zone NOT NULL,
    enviada_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT notificaciones_apoderado_motivo_check CHECK (((motivo)::text = ANY (ARRAY[('activacion'::character varying)::text, ('citacion'::character varying)::text, ('resolucion'::character varying)::text, ('seguimiento'::character varying)::text]))),
    CONSTRAINT notificaciones_apoderado_plazo_check CHECK (((plazo_apelacion_dias IS NULL) OR (plazo_apelacion_dias > 0)))
);


--
-- Name: notificaciones_apoderado_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notificaciones_apoderado_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notificaciones_apoderado_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notificaciones_apoderado_id_seq OWNED BY public.notificaciones_apoderado.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: paises; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.paises (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    codigo character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: paises_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.paises_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: paises_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.paises_id_seq OWNED BY public.paises.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: personas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personas (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    tipo_documento character varying(255) NOT NULL,
    numero_documento character varying(255) NOT NULL,
    nombres character varying(255) NOT NULL,
    primer_apellido character varying(255) NOT NULL,
    segundo_apellido character varying(255),
    fecha_nacimiento date,
    nacionalidad_id bigint,
    correo character varying(255),
    telefono character varying(255),
    direccion character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT personas_tipo_documento_check CHECK (((tipo_documento)::text = ANY (ARRAY[('rut'::character varying)::text, ('ipe'::character varying)::text, ('pasaporte'::character varying)::text, ('otro'::character varying)::text])))
);


--
-- Name: personas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personas_id_seq OWNED BY public.personas.id;


--
-- Name: procedimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.procedimientos (
    id bigint NOT NULL,
    establecimiento_id bigint,
    protocolo_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    descripcion text,
    orden smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT procedimientos_orden_check CHECK ((orden >= 0))
);


--
-- Name: procedimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.procedimientos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: procedimientos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.procedimientos_id_seq OWNED BY public.procedimientos.id;


--
-- Name: protocolos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.protocolos (
    id bigint NOT NULL,
    establecimiento_id bigint,
    nombre character varying(255) NOT NULL,
    descripcion text,
    basado_en_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: protocolos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.protocolos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: protocolos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.protocolos_id_seq OWNED BY public.protocolos.id;


--
-- Name: regiones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.regiones (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    codigo character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: regiones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.regiones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: regiones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.regiones_id_seq OWNED BY public.regiones.id;


--
-- Name: seguimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.seguimientos (
    id bigint NOT NULL,
    establecimiento_id bigint NOT NULL,
    caso_id bigint NOT NULL,
    derivacion_id bigint,
    accion_id bigint,
    descripcion text NOT NULL,
    fecha_realizacion date NOT NULL,
    notificado_a character varying(255),
    registrado_por bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: seguimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.seguimientos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: seguimientos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.seguimientos_id_seq OWNED BY public.seguimientos.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: sostenedores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sostenedores (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    rut character varying(255) NOT NULL,
    dv character varying(1) NOT NULL,
    correo character varying(255),
    telefono character varying(255),
    direccion character varying(255),
    comuna_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sostenedores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sostenedores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sostenedores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sostenedores_id_seq OWNED BY public.sostenedores.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id bigint NOT NULL,
    correo character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    es_soporte_vinkured boolean DEFAULT false NOT NULL,
    email_verified_at timestamp(0) without time zone,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: accion_responsables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accion_responsables ALTER COLUMN id SET DEFAULT nextval('public.accion_responsables_id_seq'::regclass);


--
-- Name: acciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acciones ALTER COLUMN id SET DEFAULT nextval('public.acciones_id_seq'::regclass);


--
-- Name: actas_entrevista id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actas_entrevista ALTER COLUMN id SET DEFAULT nextval('public.actas_entrevista_id_seq'::regclass);


--
-- Name: adjuntos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adjuntos ALTER COLUMN id SET DEFAULT nextval('public.adjuntos_id_seq'::regclass);


--
-- Name: alerta_respuestas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerta_respuestas ALTER COLUMN id SET DEFAULT nextval('public.alerta_respuestas_id_seq'::regclass);


--
-- Name: alertas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alertas ALTER COLUMN id SET DEFAULT nextval('public.alertas_id_seq'::regclass);


--
-- Name: anios_escolares id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anios_escolares ALTER COLUMN id SET DEFAULT nextval('public.anios_escolares_id_seq'::regclass);


--
-- Name: apoderados id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados ALTER COLUMN id SET DEFAULT nextval('public.apoderados_id_seq'::regclass);


--
-- Name: apoderados_estudiantes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados_estudiantes ALTER COLUMN id SET DEFAULT nextval('public.apoderados_estudiantes_id_seq'::regclass);


--
-- Name: asignaciones_docentes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaciones_docentes ALTER COLUMN id SET DEFAULT nextval('public.asignaciones_docentes_id_seq'::regclass);


--
-- Name: asignaturas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaturas ALTER COLUMN id SET DEFAULT nextval('public.asignaturas_id_seq'::regclass);


--
-- Name: auditoria_eventos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria_eventos ALTER COLUMN id SET DEFAULT nextval('public.auditoria_eventos_id_seq'::regclass);


--
-- Name: avisos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avisos ALTER COLUMN id SET DEFAULT nextval('public.avisos_id_seq'::regclass);


--
-- Name: cambios_documento id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cambios_documento ALTER COLUMN id SET DEFAULT nextval('public.cambios_documento_id_seq'::regclass);


--
-- Name: caso_contadores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_contadores ALTER COLUMN id SET DEFAULT nextval('public.caso_contadores_id_seq'::regclass);


--
-- Name: caso_partes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_partes ALTER COLUMN id SET DEFAULT nextval('public.caso_partes_id_seq'::regclass);


--
-- Name: casos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.casos ALTER COLUMN id SET DEFAULT nextval('public.casos_id_seq'::regclass);


--
-- Name: comunas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comunas ALTER COLUMN id SET DEFAULT nextval('public.comunas_id_seq'::regclass);


--
-- Name: configuraciones_establecimiento id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones_establecimiento ALTER COLUMN id SET DEFAULT nextval('public.configuraciones_establecimiento_id_seq'::regclass);


--
-- Name: cursos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cursos ALTER COLUMN id SET DEFAULT nextval('public.cursos_id_seq'::regclass);


--
-- Name: derivacion_externas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivacion_externas ALTER COLUMN id SET DEFAULT nextval('public.derivacion_externas_id_seq'::regclass);


--
-- Name: derivaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones ALTER COLUMN id SET DEFAULT nextval('public.derivaciones_id_seq'::regclass);


--
-- Name: dias_no_habiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dias_no_habiles ALTER COLUMN id SET DEFAULT nextval('public.dias_no_habiles_id_seq'::regclass);


--
-- Name: establecimientos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.establecimientos ALTER COLUMN id SET DEFAULT nextval('public.establecimientos_id_seq'::regclass);


--
-- Name: estudiantes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudiantes ALTER COLUMN id SET DEFAULT nextval('public.estudiantes_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: funcionario_funciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionario_funciones ALTER COLUMN id SET DEFAULT nextval('public.funcionario_funciones_id_seq'::regclass);


--
-- Name: funcionarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionarios ALTER COLUMN id SET DEFAULT nextval('public.funcionarios_id_seq'::regclass);


--
-- Name: funciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funciones ALTER COLUMN id SET DEFAULT nextval('public.funciones_id_seq'::regclass);


--
-- Name: grados id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grados ALTER COLUMN id SET DEFAULT nextval('public.grados_id_seq'::regclass);


--
-- Name: importaciones_usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones_usuarios ALTER COLUMN id SET DEFAULT nextval('public.importaciones_usuarios_id_seq'::regclass);


--
-- Name: informe_diligencias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_diligencias ALTER COLUMN id SET DEFAULT nextval('public.informe_diligencias_id_seq'::regclass);


--
-- Name: informe_medidas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_medidas ALTER COLUMN id SET DEFAULT nextval('public.informe_medidas_id_seq'::regclass);


--
-- Name: informes_resolucion id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informes_resolucion ALTER COLUMN id SET DEFAULT nextval('public.informes_resolucion_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: matriculas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matriculas ALTER COLUMN id SET DEFAULT nextval('public.matriculas_id_seq'::regclass);


--
-- Name: membresias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membresias ALTER COLUMN id SET DEFAULT nextval('public.membresias_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: niveles_ensenanza id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.niveles_ensenanza ALTER COLUMN id SET DEFAULT nextval('public.niveles_ensenanza_id_seq'::regclass);


--
-- Name: notificaciones_apoderado id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones_apoderado ALTER COLUMN id SET DEFAULT nextval('public.notificaciones_apoderado_id_seq'::regclass);


--
-- Name: paises id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paises ALTER COLUMN id SET DEFAULT nextval('public.paises_id_seq'::regclass);


--
-- Name: personas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personas ALTER COLUMN id SET DEFAULT nextval('public.personas_id_seq'::regclass);


--
-- Name: procedimientos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.procedimientos ALTER COLUMN id SET DEFAULT nextval('public.procedimientos_id_seq'::regclass);


--
-- Name: protocolos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.protocolos ALTER COLUMN id SET DEFAULT nextval('public.protocolos_id_seq'::regclass);


--
-- Name: regiones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.regiones ALTER COLUMN id SET DEFAULT nextval('public.regiones_id_seq'::regclass);


--
-- Name: seguimientos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos ALTER COLUMN id SET DEFAULT nextval('public.seguimientos_id_seq'::regclass);


--
-- Name: sostenedores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sostenedores ALTER COLUMN id SET DEFAULT nextval('public.sostenedores_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Name: accion_responsables accion_responsables_accion_id_responsable_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accion_responsables
    ADD CONSTRAINT accion_responsables_accion_id_responsable_unique UNIQUE (accion_id, responsable);


--
-- Name: accion_responsables accion_responsables_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accion_responsables
    ADD CONSTRAINT accion_responsables_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: accion_responsables accion_responsables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accion_responsables
    ADD CONSTRAINT accion_responsables_pkey PRIMARY KEY (id);


--
-- Name: acciones acciones_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acciones
    ADD CONSTRAINT acciones_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: acciones acciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acciones
    ADD CONSTRAINT acciones_pkey PRIMARY KEY (id);


--
-- Name: actas_entrevista actas_entrevista_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actas_entrevista
    ADD CONSTRAINT actas_entrevista_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: actas_entrevista actas_entrevista_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actas_entrevista
    ADD CONSTRAINT actas_entrevista_pkey PRIMARY KEY (id);


--
-- Name: adjuntos adjuntos_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adjuntos
    ADD CONSTRAINT adjuntos_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: adjuntos adjuntos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adjuntos
    ADD CONSTRAINT adjuntos_pkey PRIMARY KEY (id);


--
-- Name: alerta_respuestas alerta_respuestas_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerta_respuestas
    ADD CONSTRAINT alerta_respuestas_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: alerta_respuestas alerta_respuestas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerta_respuestas
    ADD CONSTRAINT alerta_respuestas_pkey PRIMARY KEY (id);


--
-- Name: alertas alertas_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alertas
    ADD CONSTRAINT alertas_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: alertas alertas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alertas
    ADD CONSTRAINT alertas_pkey PRIMARY KEY (id);


--
-- Name: anios_escolares anios_escolares_establecimiento_id_anio_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anios_escolares
    ADD CONSTRAINT anios_escolares_establecimiento_id_anio_unique UNIQUE (establecimiento_id, anio);


--
-- Name: anios_escolares anios_escolares_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anios_escolares
    ADD CONSTRAINT anios_escolares_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: anios_escolares anios_escolares_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anios_escolares
    ADD CONSTRAINT anios_escolares_pkey PRIMARY KEY (id);


--
-- Name: apoderados apoderados_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados
    ADD CONSTRAINT apoderados_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: apoderados_estudiantes apoderados_estudiantes_apoderado_id_estudiante_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados_estudiantes
    ADD CONSTRAINT apoderados_estudiantes_apoderado_id_estudiante_id_unique UNIQUE (apoderado_id, estudiante_id);


--
-- Name: apoderados_estudiantes apoderados_estudiantes_establecimiento_id_apoderado_id_estudian; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados_estudiantes
    ADD CONSTRAINT apoderados_estudiantes_establecimiento_id_apoderado_id_estudian UNIQUE (establecimiento_id, apoderado_id, estudiante_id);


--
-- Name: apoderados_estudiantes apoderados_estudiantes_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados_estudiantes
    ADD CONSTRAINT apoderados_estudiantes_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: apoderados_estudiantes apoderados_estudiantes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados_estudiantes
    ADD CONSTRAINT apoderados_estudiantes_pkey PRIMARY KEY (id);


--
-- Name: apoderados apoderados_persona_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados
    ADD CONSTRAINT apoderados_persona_id_unique UNIQUE (persona_id);


--
-- Name: apoderados apoderados_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados
    ADD CONSTRAINT apoderados_pkey PRIMARY KEY (id);


--
-- Name: asignaciones_docentes asignaciones_docentes_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaciones_docentes
    ADD CONSTRAINT asignaciones_docentes_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: asignaciones_docentes asignaciones_docentes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaciones_docentes
    ADD CONSTRAINT asignaciones_docentes_pkey PRIMARY KEY (id);


--
-- Name: asignaturas asignaturas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaturas
    ADD CONSTRAINT asignaturas_pkey PRIMARY KEY (id);


--
-- Name: auditoria_eventos auditoria_eventos_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria_eventos
    ADD CONSTRAINT auditoria_eventos_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: auditoria_eventos auditoria_eventos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria_eventos
    ADD CONSTRAINT auditoria_eventos_pkey PRIMARY KEY (id);


--
-- Name: avisos avisos_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avisos
    ADD CONSTRAINT avisos_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: avisos avisos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avisos
    ADD CONSTRAINT avisos_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: cambios_documento cambios_documento_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cambios_documento
    ADD CONSTRAINT cambios_documento_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: cambios_documento cambios_documento_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cambios_documento
    ADD CONSTRAINT cambios_documento_pkey PRIMARY KEY (id);


--
-- Name: caso_contadores caso_contadores_establecimiento_id_anio_escolar_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_contadores
    ADD CONSTRAINT caso_contadores_establecimiento_id_anio_escolar_id_unique UNIQUE (establecimiento_id, anio_escolar_id);


--
-- Name: caso_contadores caso_contadores_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_contadores
    ADD CONSTRAINT caso_contadores_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: caso_contadores caso_contadores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_contadores
    ADD CONSTRAINT caso_contadores_pkey PRIMARY KEY (id);


--
-- Name: caso_partes caso_partes_establecimiento_id_caso_id_persona_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_partes
    ADD CONSTRAINT caso_partes_establecimiento_id_caso_id_persona_id_unique UNIQUE (establecimiento_id, caso_id, persona_id);


--
-- Name: caso_partes caso_partes_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_partes
    ADD CONSTRAINT caso_partes_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: caso_partes caso_partes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_partes
    ADD CONSTRAINT caso_partes_pkey PRIMARY KEY (id);


--
-- Name: casos casos_establecimiento_id_anio_escolar_id_numero_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_establecimiento_id_anio_escolar_id_numero_unique UNIQUE (establecimiento_id, anio_escolar_id, numero);


--
-- Name: casos casos_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: casos casos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_pkey PRIMARY KEY (id);


--
-- Name: comunas comunas_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comunas
    ADD CONSTRAINT comunas_codigo_unique UNIQUE (codigo);


--
-- Name: comunas comunas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comunas
    ADD CONSTRAINT comunas_pkey PRIMARY KEY (id);


--
-- Name: configuraciones_establecimiento configuraciones_establecimiento_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones_establecimiento
    ADD CONSTRAINT configuraciones_establecimiento_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: configuraciones_establecimiento configuraciones_establecimiento_establecimiento_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones_establecimiento
    ADD CONSTRAINT configuraciones_establecimiento_establecimiento_id_unique UNIQUE (establecimiento_id);


--
-- Name: configuraciones_establecimiento configuraciones_establecimiento_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones_establecimiento
    ADD CONSTRAINT configuraciones_establecimiento_pkey PRIMARY KEY (id);


--
-- Name: cursos cursos_establecimiento_id_anio_escolar_id_grado_id_letra_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cursos
    ADD CONSTRAINT cursos_establecimiento_id_anio_escolar_id_grado_id_letra_unique UNIQUE (establecimiento_id, anio_escolar_id, grado_id, letra);


--
-- Name: cursos cursos_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cursos
    ADD CONSTRAINT cursos_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: cursos cursos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cursos
    ADD CONSTRAINT cursos_pkey PRIMARY KEY (id);


--
-- Name: derivacion_externas derivacion_externas_derivacion_id_destino_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivacion_externas
    ADD CONSTRAINT derivacion_externas_derivacion_id_destino_unique UNIQUE (derivacion_id, destino);


--
-- Name: derivacion_externas derivacion_externas_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivacion_externas
    ADD CONSTRAINT derivacion_externas_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: derivacion_externas derivacion_externas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivacion_externas
    ADD CONSTRAINT derivacion_externas_pkey PRIMARY KEY (id);


--
-- Name: derivaciones derivaciones_alerta_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_alerta_id_unique UNIQUE (alerta_id);


--
-- Name: derivaciones derivaciones_aviso_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_aviso_id_unique UNIQUE (aviso_id);


--
-- Name: derivaciones derivaciones_establecimiento_id_caso_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_caso_id_id_unique UNIQUE (establecimiento_id, caso_id, id);


--
-- Name: derivaciones derivaciones_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: derivaciones derivaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_pkey PRIMARY KEY (id);


--
-- Name: dias_no_habiles dias_no_habiles_establecimiento_id_fecha_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dias_no_habiles
    ADD CONSTRAINT dias_no_habiles_establecimiento_id_fecha_unique UNIQUE (establecimiento_id, fecha);


--
-- Name: dias_no_habiles dias_no_habiles_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dias_no_habiles
    ADD CONSTRAINT dias_no_habiles_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: dias_no_habiles dias_no_habiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dias_no_habiles
    ADD CONSTRAINT dias_no_habiles_pkey PRIMARY KEY (id);


--
-- Name: establecimientos establecimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.establecimientos
    ADD CONSTRAINT establecimientos_pkey PRIMARY KEY (id);


--
-- Name: establecimientos establecimientos_rbd_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.establecimientos
    ADD CONSTRAINT establecimientos_rbd_unique UNIQUE (rbd);


--
-- Name: estudiantes estudiantes_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudiantes
    ADD CONSTRAINT estudiantes_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: estudiantes estudiantes_persona_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudiantes
    ADD CONSTRAINT estudiantes_persona_id_unique UNIQUE (persona_id);


--
-- Name: estudiantes estudiantes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudiantes
    ADD CONSTRAINT estudiantes_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: funcionario_funciones funcionario_funciones_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionario_funciones
    ADD CONSTRAINT funcionario_funciones_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: funcionario_funciones funcionario_funciones_funcionario_id_funcion_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionario_funciones
    ADD CONSTRAINT funcionario_funciones_funcionario_id_funcion_id_unique UNIQUE (funcionario_id, funcion_id);


--
-- Name: funcionario_funciones funcionario_funciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionario_funciones
    ADD CONSTRAINT funcionario_funciones_pkey PRIMARY KEY (id);


--
-- Name: funcionarios funcionarios_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionarios
    ADD CONSTRAINT funcionarios_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: funcionarios funcionarios_persona_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionarios
    ADD CONSTRAINT funcionarios_persona_id_unique UNIQUE (persona_id);


--
-- Name: funcionarios funcionarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionarios
    ADD CONSTRAINT funcionarios_pkey PRIMARY KEY (id);


--
-- Name: funciones funciones_clave_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funciones
    ADD CONSTRAINT funciones_clave_unique UNIQUE (clave);


--
-- Name: funciones funciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funciones
    ADD CONSTRAINT funciones_pkey PRIMARY KEY (id);


--
-- Name: grados grados_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grados
    ADD CONSTRAINT grados_pkey PRIMARY KEY (id);


--
-- Name: importaciones_usuarios importaciones_usuarios_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones_usuarios
    ADD CONSTRAINT importaciones_usuarios_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: importaciones_usuarios importaciones_usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones_usuarios
    ADD CONSTRAINT importaciones_usuarios_pkey PRIMARY KEY (id);


--
-- Name: informe_diligencias informe_diligencias_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_diligencias
    ADD CONSTRAINT informe_diligencias_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: informe_diligencias informe_diligencias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_diligencias
    ADD CONSTRAINT informe_diligencias_pkey PRIMARY KEY (id);


--
-- Name: informe_medidas informe_medidas_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_medidas
    ADD CONSTRAINT informe_medidas_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: informe_medidas informe_medidas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_medidas
    ADD CONSTRAINT informe_medidas_pkey PRIMARY KEY (id);


--
-- Name: informes_resolucion informes_resolucion_caso_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informes_resolucion
    ADD CONSTRAINT informes_resolucion_caso_id_unique UNIQUE (caso_id);


--
-- Name: informes_resolucion informes_resolucion_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informes_resolucion
    ADD CONSTRAINT informes_resolucion_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: informes_resolucion informes_resolucion_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informes_resolucion
    ADD CONSTRAINT informes_resolucion_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: matriculas matriculas_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matriculas
    ADD CONSTRAINT matriculas_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: matriculas matriculas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matriculas
    ADD CONSTRAINT matriculas_pkey PRIMARY KEY (id);


--
-- Name: membresias membresias_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membresias
    ADD CONSTRAINT membresias_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: membresias membresias_persona_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membresias
    ADD CONSTRAINT membresias_persona_id_unique UNIQUE (persona_id);


--
-- Name: membresias membresias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membresias
    ADD CONSTRAINT membresias_pkey PRIMARY KEY (id);


--
-- Name: membresias membresias_usuario_id_establecimiento_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membresias
    ADD CONSTRAINT membresias_usuario_id_establecimiento_id_unique UNIQUE (usuario_id, establecimiento_id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: niveles_ensenanza niveles_ensenanza_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.niveles_ensenanza
    ADD CONSTRAINT niveles_ensenanza_pkey PRIMARY KEY (id);


--
-- Name: notificaciones_apoderado notificaciones_apoderado_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones_apoderado
    ADD CONSTRAINT notificaciones_apoderado_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: notificaciones_apoderado notificaciones_apoderado_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones_apoderado
    ADD CONSTRAINT notificaciones_apoderado_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: paises paises_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paises
    ADD CONSTRAINT paises_codigo_unique UNIQUE (codigo);


--
-- Name: paises paises_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paises
    ADD CONSTRAINT paises_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personas personas_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: personas personas_establecimiento_id_tipo_documento_numero_documento_uni; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_establecimiento_id_tipo_documento_numero_documento_uni UNIQUE (establecimiento_id, tipo_documento, numero_documento);


--
-- Name: personas personas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_pkey PRIMARY KEY (id);


--
-- Name: procedimientos procedimientos_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.procedimientos
    ADD CONSTRAINT procedimientos_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: procedimientos procedimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.procedimientos
    ADD CONSTRAINT procedimientos_pkey PRIMARY KEY (id);


--
-- Name: protocolos protocolos_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.protocolos
    ADD CONSTRAINT protocolos_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: protocolos protocolos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.protocolos
    ADD CONSTRAINT protocolos_pkey PRIMARY KEY (id);


--
-- Name: regiones regiones_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.regiones
    ADD CONSTRAINT regiones_codigo_unique UNIQUE (codigo);


--
-- Name: regiones regiones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.regiones
    ADD CONSTRAINT regiones_pkey PRIMARY KEY (id);


--
-- Name: seguimientos seguimientos_establecimiento_id_caso_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos
    ADD CONSTRAINT seguimientos_establecimiento_id_caso_id_id_unique UNIQUE (establecimiento_id, caso_id, id);


--
-- Name: seguimientos seguimientos_establecimiento_id_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos
    ADD CONSTRAINT seguimientos_establecimiento_id_id_unique UNIQUE (establecimiento_id, id);


--
-- Name: seguimientos seguimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos
    ADD CONSTRAINT seguimientos_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sostenedores sostenedores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sostenedores
    ADD CONSTRAINT sostenedores_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_correo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_correo_unique UNIQUE (correo);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: adjuntos_adjuntable_tipo_adjuntable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adjuntos_adjuntable_tipo_adjuntable_id_index ON public.adjuntos USING btree (adjuntable_tipo, adjuntable_id);


--
-- Name: alerta_respuestas_alerta_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX alerta_respuestas_alerta_unique ON public.alerta_respuestas USING btree (alerta_id);


--
-- Name: apoderados_estudiantes_titular_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX apoderados_estudiantes_titular_unique ON public.apoderados_estudiantes USING btree (estudiante_id) WHERE es_titular;


--
-- Name: asignaciones_docentes_jefe_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX asignaciones_docentes_jefe_unique ON public.asignaciones_docentes USING btree (curso_id) WHERE es_jefatura;


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: dias_no_habiles_fecha_nacional_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX dias_no_habiles_fecha_nacional_unique ON public.dias_no_habiles USING btree (fecha) WHERE (establecimiento_id IS NULL);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: matriculas_vigente_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX matriculas_vigente_unique ON public.matriculas USING btree (estudiante_id) WHERE (fecha_retiro IS NULL);


--
-- Name: membresias_soporte_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX membresias_soporte_unique ON public.membresias USING btree (usuario_id) WHERE (establecimiento_id IS NULL);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: accion_responsables accion_responsables_padre_plantilla; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER accion_responsables_padre_plantilla BEFORE INSERT OR UPDATE ON public.accion_responsables FOR EACH ROW EXECUTE FUNCTION public.exigir_padre_plantilla('acciones', 'accion_id');


--
-- Name: acciones acciones_establecimiento_id_inmutable; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER acciones_establecimiento_id_inmutable BEFORE UPDATE ON public.acciones FOR EACH ROW EXECUTE FUNCTION public.establecimiento_id_inmutable();


--
-- Name: acciones acciones_padre_plantilla; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER acciones_padre_plantilla BEFORE INSERT OR UPDATE ON public.acciones FOR EACH ROW EXECUTE FUNCTION public.exigir_padre_plantilla('procedimientos', 'procedimiento_id');


--
-- Name: alertas alertas_transicion_estado; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER alertas_transicion_estado BEFORE UPDATE ON public.alertas FOR EACH ROW EXECUTE FUNCTION public.alertas_validar_transicion_estado();


--
-- Name: auditoria_eventos auditoria_eventos_no_truncate; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER auditoria_eventos_no_truncate BEFORE TRUNCATE ON public.auditoria_eventos FOR EACH STATEMENT EXECUTE FUNCTION public.auditoria_eventos_solo_insercion();


--
-- Name: auditoria_eventos auditoria_eventos_no_update_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER auditoria_eventos_no_update_delete BEFORE DELETE OR UPDATE ON public.auditoria_eventos FOR EACH ROW EXECUTE FUNCTION public.auditoria_eventos_solo_insercion();


--
-- Name: avisos avisos_no_se_borran; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER avisos_no_se_borran BEFORE DELETE ON public.avisos FOR EACH ROW EXECUTE FUNCTION public.avisos_no_se_borran();


--
-- Name: avisos avisos_solo_decision_editable; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER avisos_solo_decision_editable BEFORE UPDATE ON public.avisos FOR EACH ROW EXECUTE FUNCTION public.avisos_solo_decision_editable();


--
-- Name: casos casos_alta_requiere_informe_aprobado; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER casos_alta_requiere_informe_aprobado BEFORE INSERT OR UPDATE ON public.casos FOR EACH ROW EXECUTE FUNCTION public.casos_alta_requiere_informe_aprobado();


--
-- Name: casos casos_numero_desde_contador; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER casos_numero_desde_contador BEFORE INSERT ON public.casos FOR EACH ROW EXECUTE FUNCTION public.casos_numero_desde_contador();


--
-- Name: casos casos_numero_inmutable; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER casos_numero_inmutable BEFORE UPDATE ON public.casos FOR EACH ROW EXECUTE FUNCTION public.casos_numero_inmutable();


--
-- Name: derivaciones derivaciones_origen_coincide_con_aviso; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER derivaciones_origen_coincide_con_aviso BEFORE INSERT OR UPDATE ON public.derivaciones FOR EACH ROW EXECUTE FUNCTION public.derivaciones_origen_coincide_con_aviso();


--
-- Name: informes_resolucion informes_aprobado_mientras_caso_en_alta; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER informes_aprobado_mientras_caso_en_alta BEFORE DELETE OR UPDATE ON public.informes_resolucion FOR EACH ROW EXECUTE FUNCTION public.informes_aprobado_mientras_caso_en_alta();


--
-- Name: membresias membresias_soporte_requiere_bandera; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER membresias_soporte_requiere_bandera BEFORE INSERT OR UPDATE ON public.membresias FOR EACH ROW EXECUTE FUNCTION public.membresias_soporte_requiere_bandera();


--
-- Name: procedimientos procedimientos_establecimiento_id_inmutable; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER procedimientos_establecimiento_id_inmutable BEFORE UPDATE ON public.procedimientos FOR EACH ROW EXECUTE FUNCTION public.establecimiento_id_inmutable();


--
-- Name: procedimientos procedimientos_padre_plantilla; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER procedimientos_padre_plantilla BEFORE INSERT OR UPDATE ON public.procedimientos FOR EACH ROW EXECUTE FUNCTION public.exigir_padre_plantilla('protocolos', 'protocolo_id');


--
-- Name: protocolos protocolos_basado_en_plantilla; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER protocolos_basado_en_plantilla BEFORE INSERT OR UPDATE ON public.protocolos FOR EACH ROW EXECUTE FUNCTION public.protocolos_basado_en_plantilla();


--
-- Name: protocolos protocolos_establecimiento_id_inmutable; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER protocolos_establecimiento_id_inmutable BEFORE UPDATE ON public.protocolos FOR EACH ROW EXECUTE FUNCTION public.establecimiento_id_inmutable();


--
-- Name: usuarios usuarios_bandera_soporte_con_membresia; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER usuarios_bandera_soporte_con_membresia BEFORE UPDATE ON public.usuarios FOR EACH ROW EXECUTE FUNCTION public.usuarios_bandera_soporte_con_membresia();


--
-- Name: accion_responsables accion_responsables_accion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accion_responsables
    ADD CONSTRAINT accion_responsables_accion_id_foreign FOREIGN KEY (accion_id) REFERENCES public.acciones(id) ON DELETE RESTRICT;


--
-- Name: accion_responsables accion_responsables_establecimiento_id_accion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accion_responsables
    ADD CONSTRAINT accion_responsables_establecimiento_id_accion_id_foreign FOREIGN KEY (establecimiento_id, accion_id) REFERENCES public.acciones(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: accion_responsables accion_responsables_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accion_responsables
    ADD CONSTRAINT accion_responsables_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: acciones acciones_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acciones
    ADD CONSTRAINT acciones_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: acciones acciones_establecimiento_id_procedimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acciones
    ADD CONSTRAINT acciones_establecimiento_id_procedimiento_id_foreign FOREIGN KEY (establecimiento_id, procedimiento_id) REFERENCES public.procedimientos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: acciones acciones_procedimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acciones
    ADD CONSTRAINT acciones_procedimiento_id_foreign FOREIGN KEY (procedimiento_id) REFERENCES public.procedimientos(id) ON DELETE RESTRICT;


--
-- Name: actas_entrevista actas_entrevista_establecimiento_id_caso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actas_entrevista
    ADD CONSTRAINT actas_entrevista_establecimiento_id_caso_id_foreign FOREIGN KEY (establecimiento_id, caso_id) REFERENCES public.casos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: actas_entrevista actas_entrevista_establecimiento_id_caso_id_persona_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actas_entrevista
    ADD CONSTRAINT actas_entrevista_establecimiento_id_caso_id_persona_id_foreign FOREIGN KEY (establecimiento_id, caso_id, persona_id) REFERENCES public.caso_partes(establecimiento_id, caso_id, persona_id) ON DELETE RESTRICT;


--
-- Name: actas_entrevista actas_entrevista_establecimiento_id_caso_id_seguimiento_id_fore; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actas_entrevista
    ADD CONSTRAINT actas_entrevista_establecimiento_id_caso_id_seguimiento_id_fore FOREIGN KEY (establecimiento_id, caso_id, seguimiento_id) REFERENCES public.seguimientos(establecimiento_id, caso_id, id) ON DELETE RESTRICT;


--
-- Name: actas_entrevista actas_entrevista_establecimiento_id_entrevistador_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actas_entrevista
    ADD CONSTRAINT actas_entrevista_establecimiento_id_entrevistador_id_foreign FOREIGN KEY (establecimiento_id, entrevistador_id) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: actas_entrevista actas_entrevista_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actas_entrevista
    ADD CONSTRAINT actas_entrevista_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: adjuntos adjuntos_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adjuntos
    ADD CONSTRAINT adjuntos_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: adjuntos adjuntos_subido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adjuntos
    ADD CONSTRAINT adjuntos_subido_por_foreign FOREIGN KEY (subido_por) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: alerta_respuestas alerta_respuestas_establecimiento_id_alerta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerta_respuestas
    ADD CONSTRAINT alerta_respuestas_establecimiento_id_alerta_id_foreign FOREIGN KEY (establecimiento_id, alerta_id) REFERENCES public.alertas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: alerta_respuestas alerta_respuestas_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerta_respuestas
    ADD CONSTRAINT alerta_respuestas_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: alerta_respuestas alerta_respuestas_establecimiento_id_funcionario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerta_respuestas
    ADD CONSTRAINT alerta_respuestas_establecimiento_id_funcionario_id_foreign FOREIGN KEY (establecimiento_id, funcionario_id) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: alerta_respuestas alerta_respuestas_protocolo_anterior_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerta_respuestas
    ADD CONSTRAINT alerta_respuestas_protocolo_anterior_fk FOREIGN KEY (establecimiento_id, protocolo_anterior_id) REFERENCES public.protocolos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: alertas alertas_establecimiento_id_apoderado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alertas
    ADD CONSTRAINT alertas_establecimiento_id_apoderado_id_foreign FOREIGN KEY (establecimiento_id, apoderado_id) REFERENCES public.apoderados(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: alertas alertas_establecimiento_id_estudiante_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alertas
    ADD CONSTRAINT alertas_establecimiento_id_estudiante_id_foreign FOREIGN KEY (establecimiento_id, estudiante_id) REFERENCES public.estudiantes(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: alertas alertas_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alertas
    ADD CONSTRAINT alertas_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: alertas alertas_establecimiento_id_protocolo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alertas
    ADD CONSTRAINT alertas_establecimiento_id_protocolo_id_foreign FOREIGN KEY (establecimiento_id, protocolo_id) REFERENCES public.protocolos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: anios_escolares anios_escolares_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anios_escolares
    ADD CONSTRAINT anios_escolares_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: apoderados apoderados_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados
    ADD CONSTRAINT apoderados_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: apoderados apoderados_establecimiento_id_persona_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados
    ADD CONSTRAINT apoderados_establecimiento_id_persona_id_foreign FOREIGN KEY (establecimiento_id, persona_id) REFERENCES public.personas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: apoderados_estudiantes apoderados_estudiantes_establecimiento_id_apoderado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados_estudiantes
    ADD CONSTRAINT apoderados_estudiantes_establecimiento_id_apoderado_id_foreign FOREIGN KEY (establecimiento_id, apoderado_id) REFERENCES public.apoderados(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: apoderados_estudiantes apoderados_estudiantes_establecimiento_id_estudiante_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados_estudiantes
    ADD CONSTRAINT apoderados_estudiantes_establecimiento_id_estudiante_id_foreign FOREIGN KEY (establecimiento_id, estudiante_id) REFERENCES public.estudiantes(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: apoderados_estudiantes apoderados_estudiantes_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apoderados_estudiantes
    ADD CONSTRAINT apoderados_estudiantes_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: asignaciones_docentes asignaciones_docentes_asignatura_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaciones_docentes
    ADD CONSTRAINT asignaciones_docentes_asignatura_id_foreign FOREIGN KEY (asignatura_id) REFERENCES public.asignaturas(id) ON DELETE RESTRICT;


--
-- Name: asignaciones_docentes asignaciones_docentes_establecimiento_id_curso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaciones_docentes
    ADD CONSTRAINT asignaciones_docentes_establecimiento_id_curso_id_foreign FOREIGN KEY (establecimiento_id, curso_id) REFERENCES public.cursos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: asignaciones_docentes asignaciones_docentes_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaciones_docentes
    ADD CONSTRAINT asignaciones_docentes_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: asignaciones_docentes asignaciones_docentes_establecimiento_id_funcionario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asignaciones_docentes
    ADD CONSTRAINT asignaciones_docentes_establecimiento_id_funcionario_id_foreign FOREIGN KEY (establecimiento_id, funcionario_id) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: auditoria_eventos auditoria_eventos_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria_eventos
    ADD CONSTRAINT auditoria_eventos_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: auditoria_eventos auditoria_eventos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria_eventos
    ADD CONSTRAINT auditoria_eventos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: avisos avisos_establecimiento_id_decidido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avisos
    ADD CONSTRAINT avisos_establecimiento_id_decidido_por_foreign FOREIGN KEY (establecimiento_id, decidido_por) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: avisos avisos_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avisos
    ADD CONSTRAINT avisos_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: avisos avisos_establecimiento_id_informante_persona_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avisos
    ADD CONSTRAINT avisos_establecimiento_id_informante_persona_id_foreign FOREIGN KEY (establecimiento_id, informante_persona_id) REFERENCES public.personas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: avisos avisos_establecimiento_id_recibido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avisos
    ADD CONSTRAINT avisos_establecimiento_id_recibido_por_foreign FOREIGN KEY (establecimiento_id, recibido_por) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: cambios_documento cambios_documento_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cambios_documento
    ADD CONSTRAINT cambios_documento_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: cambios_documento cambios_documento_establecimiento_id_persona_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cambios_documento
    ADD CONSTRAINT cambios_documento_establecimiento_id_persona_id_foreign FOREIGN KEY (establecimiento_id, persona_id) REFERENCES public.personas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: cambios_documento cambios_documento_registrado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cambios_documento
    ADD CONSTRAINT cambios_documento_registrado_por_foreign FOREIGN KEY (registrado_por) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: caso_contadores caso_contadores_establecimiento_id_anio_escolar_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_contadores
    ADD CONSTRAINT caso_contadores_establecimiento_id_anio_escolar_id_foreign FOREIGN KEY (establecimiento_id, anio_escolar_id) REFERENCES public.anios_escolares(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: caso_contadores caso_contadores_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_contadores
    ADD CONSTRAINT caso_contadores_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: caso_partes caso_partes_establecimiento_id_caso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_partes
    ADD CONSTRAINT caso_partes_establecimiento_id_caso_id_foreign FOREIGN KEY (establecimiento_id, caso_id) REFERENCES public.casos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: caso_partes caso_partes_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_partes
    ADD CONSTRAINT caso_partes_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: caso_partes caso_partes_establecimiento_id_persona_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caso_partes
    ADD CONSTRAINT caso_partes_establecimiento_id_persona_id_foreign FOREIGN KEY (establecimiento_id, persona_id) REFERENCES public.personas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: casos casos_establecimiento_id_abierto_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_establecimiento_id_abierto_por_foreign FOREIGN KEY (establecimiento_id, abierto_por) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: casos casos_establecimiento_id_alta_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_establecimiento_id_alta_por_foreign FOREIGN KEY (establecimiento_id, alta_por) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: casos casos_establecimiento_id_anio_escolar_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_establecimiento_id_anio_escolar_id_foreign FOREIGN KEY (establecimiento_id, anio_escolar_id) REFERENCES public.anios_escolares(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: casos casos_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: comunas comunas_region_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comunas
    ADD CONSTRAINT comunas_region_id_foreign FOREIGN KEY (region_id) REFERENCES public.regiones(id) ON DELETE RESTRICT;


--
-- Name: configuraciones_establecimiento configuraciones_establecimiento_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones_establecimiento
    ADD CONSTRAINT configuraciones_establecimiento_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: cursos cursos_establecimiento_id_anio_escolar_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cursos
    ADD CONSTRAINT cursos_establecimiento_id_anio_escolar_id_foreign FOREIGN KEY (establecimiento_id, anio_escolar_id) REFERENCES public.anios_escolares(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: cursos cursos_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cursos
    ADD CONSTRAINT cursos_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: cursos cursos_grado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cursos
    ADD CONSTRAINT cursos_grado_id_foreign FOREIGN KEY (grado_id) REFERENCES public.grados(id) ON DELETE RESTRICT;


--
-- Name: derivacion_externas derivacion_externas_establecimiento_id_derivacion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivacion_externas
    ADD CONSTRAINT derivacion_externas_establecimiento_id_derivacion_id_foreign FOREIGN KEY (establecimiento_id, derivacion_id) REFERENCES public.derivaciones(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: derivacion_externas derivacion_externas_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivacion_externas
    ADD CONSTRAINT derivacion_externas_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_alerta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_alerta_id_foreign FOREIGN KEY (establecimiento_id, alerta_id) REFERENCES public.alertas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_aviso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_aviso_id_foreign FOREIGN KEY (establecimiento_id, aviso_id) REFERENCES public.avisos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_caso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_caso_id_foreign FOREIGN KEY (establecimiento_id, caso_id) REFERENCES public.casos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_derivado_a_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_derivado_a_foreign FOREIGN KEY (establecimiento_id, derivado_a) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_derivado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_derivado_por_foreign FOREIGN KEY (establecimiento_id, derivado_por) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_protocolo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_protocolo_id_foreign FOREIGN KEY (establecimiento_id, protocolo_id) REFERENCES public.protocolos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_protocolo_responsable_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_protocolo_responsable_id_foreig FOREIGN KEY (establecimiento_id, protocolo_responsable_id) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: derivaciones derivaciones_establecimiento_id_recibido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.derivaciones
    ADD CONSTRAINT derivaciones_establecimiento_id_recibido_por_foreign FOREIGN KEY (establecimiento_id, recibido_por) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: dias_no_habiles dias_no_habiles_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dias_no_habiles
    ADD CONSTRAINT dias_no_habiles_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: establecimientos establecimientos_comuna_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.establecimientos
    ADD CONSTRAINT establecimientos_comuna_id_foreign FOREIGN KEY (comuna_id) REFERENCES public.comunas(id) ON DELETE RESTRICT;


--
-- Name: establecimientos establecimientos_sostenedor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.establecimientos
    ADD CONSTRAINT establecimientos_sostenedor_id_foreign FOREIGN KEY (sostenedor_id) REFERENCES public.sostenedores(id) ON DELETE RESTRICT;


--
-- Name: estudiantes estudiantes_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudiantes
    ADD CONSTRAINT estudiantes_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: estudiantes estudiantes_establecimiento_id_persona_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudiantes
    ADD CONSTRAINT estudiantes_establecimiento_id_persona_id_foreign FOREIGN KEY (establecimiento_id, persona_id) REFERENCES public.personas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: funcionario_funciones funcionario_funciones_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionario_funciones
    ADD CONSTRAINT funcionario_funciones_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: funcionario_funciones funcionario_funciones_establecimiento_id_funcionario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionario_funciones
    ADD CONSTRAINT funcionario_funciones_establecimiento_id_funcionario_id_foreign FOREIGN KEY (establecimiento_id, funcionario_id) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: funcionario_funciones funcionario_funciones_funcion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionario_funciones
    ADD CONSTRAINT funcionario_funciones_funcion_id_foreign FOREIGN KEY (funcion_id) REFERENCES public.funciones(id) ON DELETE RESTRICT;


--
-- Name: funcionarios funcionarios_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionarios
    ADD CONSTRAINT funcionarios_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: funcionarios funcionarios_establecimiento_id_persona_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funcionarios
    ADD CONSTRAINT funcionarios_establecimiento_id_persona_id_foreign FOREIGN KEY (establecimiento_id, persona_id) REFERENCES public.personas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: grados grados_nivel_ensenanza_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grados
    ADD CONSTRAINT grados_nivel_ensenanza_id_foreign FOREIGN KEY (nivel_ensenanza_id) REFERENCES public.niveles_ensenanza(id) ON DELETE RESTRICT;


--
-- Name: importaciones_usuarios importaciones_usuarios_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones_usuarios
    ADD CONSTRAINT importaciones_usuarios_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: importaciones_usuarios importaciones_usuarios_establecimiento_id_membresia_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones_usuarios
    ADD CONSTRAINT importaciones_usuarios_establecimiento_id_membresia_id_foreign FOREIGN KEY (establecimiento_id, membresia_id) REFERENCES public.membresias(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: informe_diligencias informe_diligencias_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_diligencias
    ADD CONSTRAINT informe_diligencias_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: informe_diligencias informe_diligencias_establecimiento_id_informe_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_diligencias
    ADD CONSTRAINT informe_diligencias_establecimiento_id_informe_id_foreign FOREIGN KEY (establecimiento_id, informe_id) REFERENCES public.informes_resolucion(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: informe_medidas informe_medidas_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_medidas
    ADD CONSTRAINT informe_medidas_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: informe_medidas informe_medidas_establecimiento_id_informe_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informe_medidas
    ADD CONSTRAINT informe_medidas_establecimiento_id_informe_id_foreign FOREIGN KEY (establecimiento_id, informe_id) REFERENCES public.informes_resolucion(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: informes_resolucion informes_resolucion_establecimiento_id_aprobado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informes_resolucion
    ADD CONSTRAINT informes_resolucion_establecimiento_id_aprobado_por_foreign FOREIGN KEY (establecimiento_id, aprobado_por) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: informes_resolucion informes_resolucion_establecimiento_id_caso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informes_resolucion
    ADD CONSTRAINT informes_resolucion_establecimiento_id_caso_id_foreign FOREIGN KEY (establecimiento_id, caso_id) REFERENCES public.casos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: informes_resolucion informes_resolucion_establecimiento_id_encargado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informes_resolucion
    ADD CONSTRAINT informes_resolucion_establecimiento_id_encargado_id_foreign FOREIGN KEY (establecimiento_id, encargado_id) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: informes_resolucion informes_resolucion_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.informes_resolucion
    ADD CONSTRAINT informes_resolucion_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: matriculas matriculas_establecimiento_id_curso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matriculas
    ADD CONSTRAINT matriculas_establecimiento_id_curso_id_foreign FOREIGN KEY (establecimiento_id, curso_id) REFERENCES public.cursos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: matriculas matriculas_establecimiento_id_estudiante_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matriculas
    ADD CONSTRAINT matriculas_establecimiento_id_estudiante_id_foreign FOREIGN KEY (establecimiento_id, estudiante_id) REFERENCES public.estudiantes(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: matriculas matriculas_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matriculas
    ADD CONSTRAINT matriculas_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: membresias membresias_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membresias
    ADD CONSTRAINT membresias_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: membresias membresias_establecimiento_id_persona_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membresias
    ADD CONSTRAINT membresias_establecimiento_id_persona_id_foreign FOREIGN KEY (establecimiento_id, persona_id) REFERENCES public.personas(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: membresias membresias_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membresias
    ADD CONSTRAINT membresias_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: notificaciones_apoderado notificaciones_apoderado_establecimiento_id_caso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones_apoderado
    ADD CONSTRAINT notificaciones_apoderado_establecimiento_id_caso_id_foreign FOREIGN KEY (establecimiento_id, caso_id) REFERENCES public.casos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: notificaciones_apoderado notificaciones_apoderado_establecimiento_id_enviada_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones_apoderado
    ADD CONSTRAINT notificaciones_apoderado_establecimiento_id_enviada_por_foreign FOREIGN KEY (establecimiento_id, enviada_por) REFERENCES public.funcionarios(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: notificaciones_apoderado notificaciones_apoderado_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones_apoderado
    ADD CONSTRAINT notificaciones_apoderado_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: notificaciones_apoderado notificaciones_apoderado_vinculo_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones_apoderado
    ADD CONSTRAINT notificaciones_apoderado_vinculo_foreign FOREIGN KEY (establecimiento_id, apoderado_id, estudiante_id) REFERENCES public.apoderados_estudiantes(establecimiento_id, apoderado_id, estudiante_id) ON DELETE RESTRICT;


--
-- Name: personas personas_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: personas personas_nacionalidad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_nacionalidad_id_foreign FOREIGN KEY (nacionalidad_id) REFERENCES public.paises(id) ON DELETE RESTRICT;


--
-- Name: procedimientos procedimientos_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.procedimientos
    ADD CONSTRAINT procedimientos_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: procedimientos procedimientos_establecimiento_id_protocolo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.procedimientos
    ADD CONSTRAINT procedimientos_establecimiento_id_protocolo_id_foreign FOREIGN KEY (establecimiento_id, protocolo_id) REFERENCES public.protocolos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: procedimientos procedimientos_protocolo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.procedimientos
    ADD CONSTRAINT procedimientos_protocolo_id_foreign FOREIGN KEY (protocolo_id) REFERENCES public.protocolos(id) ON DELETE RESTRICT;


--
-- Name: protocolos protocolos_basado_en_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.protocolos
    ADD CONSTRAINT protocolos_basado_en_id_foreign FOREIGN KEY (basado_en_id) REFERENCES public.protocolos(id) ON DELETE RESTRICT;


--
-- Name: protocolos protocolos_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.protocolos
    ADD CONSTRAINT protocolos_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: seguimientos seguimientos_establecimiento_id_accion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos
    ADD CONSTRAINT seguimientos_establecimiento_id_accion_id_foreign FOREIGN KEY (establecimiento_id, accion_id) REFERENCES public.acciones(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: seguimientos seguimientos_establecimiento_id_caso_id_derivacion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos
    ADD CONSTRAINT seguimientos_establecimiento_id_caso_id_derivacion_id_foreign FOREIGN KEY (establecimiento_id, caso_id, derivacion_id) REFERENCES public.derivaciones(establecimiento_id, caso_id, id) ON DELETE RESTRICT;


--
-- Name: seguimientos seguimientos_establecimiento_id_caso_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos
    ADD CONSTRAINT seguimientos_establecimiento_id_caso_id_foreign FOREIGN KEY (establecimiento_id, caso_id) REFERENCES public.casos(establecimiento_id, id) ON DELETE RESTRICT;


--
-- Name: seguimientos seguimientos_establecimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos
    ADD CONSTRAINT seguimientos_establecimiento_id_foreign FOREIGN KEY (establecimiento_id) REFERENCES public.establecimientos(id) ON DELETE RESTRICT;


--
-- Name: seguimientos seguimientos_registrado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seguimientos
    ADD CONSTRAINT seguimientos_registrado_por_foreign FOREIGN KEY (registrado_por) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: sostenedores sostenedores_comuna_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sostenedores
    ADD CONSTRAINT sostenedores_comuna_id_foreign FOREIGN KEY (comuna_id) REFERENCES public.comunas(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

