<!-- @host-owned Copied from Vinkured CONTEXT.md — do not modify here; replace with the host class at integration. -->
# VINKURED

Sistema de gestión integrado para establecimientos educacionales, ofrecido como producto comercial y compuesto por módulos. El primer módulo es Convivencia Escolar.

## Language

**VINKURED**:
El producto: un sistema de gestión integrado para establecimientos educacionales, que crece por módulos.
_Avoid_: Plataforma (salvo en lenguaje coloquial), Gestor

**Módulo**:
Área funcional de VINKURED que un Establecimiento incorpora. Convivencia Escolar es el primero.
_Avoid_: Funcionalidad, Componente

**Convivencia Escolar**:
Módulo de VINKURED para prevenir, registrar y dar seguimiento a los conflictos de la comunidad educativa.
_Avoid_: C.E., Gestor de Convivencia

### Organización

**Establecimiento**:
Institución educacional identificada por su RBD; es la unidad que incorpora VINKURED y cuyos datos se mantienen separados de los de otros.
_Avoid_: Colegio, Escuela, Institución

**Sostenedor**:
Persona o entidad propietaria de uno o más Establecimientos.
_Avoid_: Dueño, Cliente

**Administrador**:
Usuario de un Establecimiento que carga y gestiona a los demás usuarios, de a uno o de forma masiva.
_Avoid_: Admin, Superusuario

**Coordinador de Convivencia**:
Usuario de un Establecimiento que lidera el equipo de convivencia y decide si una situación se formaliza como Derivación. Es el término de la Ley 21.809 (Coordinador de Convivencia Educativa).
_Avoid_: Encargado de Convivencia

### Identificación

**IPE**:
Identificador provisorio con que se matricula a un estudiante que aún no tiene RUT.
_Avoid_: RUT provisorio

### Convivencia

**Caso**:
Apertura de una investigación por un conflicto dentro de la comunidad educativa, por ejemplo bullying a un estudiante o peleas entre estudiantes, entre docentes o entre apoderados. Se abre al formalizar la situación y agrupa una o más Derivaciones y su Seguimiento hasta el Alta; por lo general tiene una sola Derivación.
_Avoid_: Incidente, Denuncia, Expediente

**Parte**:
Persona que participa en un Caso: afectado, involucrado, informante o testigo. Puede ser estudiante, apoderado o funcionario.
_Avoid_: Implicado, Acusado

**Alerta preventiva**:
Mecanismo con que un Apoderado da aviso de una situación de acoso, violencia u otra que afecta a su estudiante. Un docente o el Coordinador de Convivencia la responde e informa que se tomarán acciones. Es exclusiva del Apoderado.
_Avoid_: Denuncia, Reclamo

**Alerta sin respuesta**:
Alerta preventiva cuyo plazo venció sin que nadie la respondiera. Se conserva registrada por transparencia.
_Avoid_: Alerta ignorada, Alerta perdida

**Aviso**:
Comunicación de una situación recibida por un miembro del Establecimiento (funcionario) o presentada en persona por un Apoderado, previa a la Derivación. El Coordinador de Convivencia decide si se formaliza como Derivación; si no, queda registrada como descartada. Distinta de la Alerta preventiva, que es exclusiva del Apoderado.
_Avoid_: Denuncia, Reporte

**Derivación**:
Formalización de una situación que el Establecimiento debe mitigar. Nace de una Alerta preventiva completada, o de un Aviso de cualquier miembro del Establecimiento (estudiante, docente, profesional, directivo) o de un Apoderado que acude en persona. El Coordinador de Convivencia decide si corresponde. Toda Derivación pertenece a un Caso.
_Avoid_: Reporte

**Seguimiento**:
Registro de las acciones realizadas, con su fecha, sobre un Caso hasta su Alta.
_Avoid_: Bitácora

**Alta**:
Cierre formal de un Caso cuando se da por resuelto.
_Avoid_: Cierre administrativo

### Protocolos

**Protocolo**:
Conjunto de Procedimientos que el Establecimiento define para un tipo de situación de convivencia, por ejemplo violencia escolar.
_Avoid_: Reglamento, Plan

**Procedimiento**:
Secuencia de Acciones dentro de un Protocolo para una situación específica, por ejemplo maltrato escolar entre pares.
_Avoid_: Flujo, Proceso

**Acción**:
Paso concreto de un Procedimiento, con un responsable directo, medidas remediales y un plazo de respuesta en días.
_Avoid_: Tarea, Paso

### Documentos normativos

**RICE**:
Reglamento Interno de Convivencia Escolar del Establecimiento, disponible para lectura y descarga de todos los usuarios.
_Avoid_: Reglamento, Manual

**PGCE**:
Plan de Gestión de la Convivencia Escolar del Establecimiento: objetivos, matriz de actividades y cronograma anual.
_Avoid_: Plan de convivencia, PGE
