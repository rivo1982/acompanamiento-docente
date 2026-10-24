<?php

namespace App\Support\Importacion;

use App\Exceptions\RutInvalido;
use App\Support\Rut;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Valida la planilla de carga masiva de funcionarios (Q18) sin tocar la base de datos. Los mensajes hablan solo del
 * contenido del archivo: nunca dicen si una persona o un correo ya existe en VINKURED (ADR-0005).
 */
final class ValidadorDePlanilla
{
    public const MAXIMO_DE_FILAS = 2000;

    public const MAXIMO_DEL_TELEFONO = 30;

    public const MAXIMO_DEL_NOMBRE = 100;

    /** Columnas obligatorias de la fila 1, en orden. */
    public const ENCABEZADO = ['RUT', 'Nombres', 'Primer apellido', 'Segundo apellido', 'Correo', 'Teléfono', 'Función'];

    /** Nombre de campo de cada columna de ENCABEZADO, en el mismo orden (claves de ValidacionDeFuncionario). */
    public const CAMPOS = ['rut', 'nombres', 'primer_apellido', 'segundo_apellido', 'correo', 'telefono', 'funcion'];

    /** Clave de los errores que no son de una columna (datos fuera de las columnas de la planilla). */
    public const CAMPO_FILA = 'fila';

    /** Función escrita en la planilla (normalizada) => funciones.categoria de Vinkured. */
    private const CATEGORIAS = [
        'docente' => 'docente',
        'directivo' => 'directivo',
        'asistente' => 'asistente',
        'profesional de apoyo' => 'profesional_apoyo',
    ];

    /**
     * @param  iterable<array-key, array<int, mixed>>  $filas  filas en el orden de la hoja, incluidas las vacías, para que
     *                                                         la posición coincida con el número de fila de Excel
     */
    public function validar(iterable $filas): ResultadoDeValidacion
    {
        $numeroDeFila = 0;
        $hayEncabezado = false;
        $cantidadDeDatos = 0;
        $filasValidas = [];
        $erroresPorFila = [];
        /** @var array<string, int> $primeraFilaDelRut */
        $primeraFilaDelRut = [];
        /** @var array<string, int> $primeraFilaDelCorreo */
        $primeraFilaDelCorreo = [];

        foreach ($filas as $celdasCrudas) {
            $numeroDeFila++;
            $celdas = array_map($this->texto(...), array_values($celdasCrudas));

            if (implode('', $celdas) === '') {
                continue;
            }

            if (! $hayEncabezado) {
                if ($numeroDeFila !== 1 || ! $this->esEncabezadoValido($celdas)) {
                    return ResultadoDeValidacion::conErrorDelArchivo(self::mensajeDeEncabezado());
                }
                $hayEncabezado = true;

                continue;
            }

            if (++$cantidadDeDatos > self::MAXIMO_DE_FILAS) {
                return ResultadoDeValidacion::conErrorDelArchivo('La planilla tiene más de '.number_format(self::MAXIMO_DE_FILAS, 0, ',', '.').' funcionarios. Dividirla en archivos más pequeños.');
            }

            $validacion = $this->validarFila($numeroDeFila, $celdas, $primeraFilaDelRut, $primeraFilaDelCorreo);

            if ($validacion->fila === null) {
                $erroresPorFila[$numeroDeFila] = $validacion->mensajes();
            } else {
                $filasValidas[] = $validacion->fila;
            }
        }

        if (! $hayEncabezado) {
            return ResultadoDeValidacion::conErrorDelArchivo('La planilla está vacía.');
        }

        if ($cantidadDeDatos === 0) {
            return ResultadoDeValidacion::conErrorDelArchivo('La planilla no tiene funcionarios bajo el encabezado.');
        }

        return new ResultadoDeValidacion([], $filasValidas, $erroresPorFila);
    }

    /**
     * Valida UN funcionario con las mismas reglas y mensajes que una fila de la planilla (carga unitaria, Q18). Las
     * celdas van en el orden de ENCABEZADO; los errores quedan por campo (CAMPOS) para mostrarlos junto a cada uno.
     *
     * @param  array<int, mixed>  $celdas
     */
    public function validarFuncionario(array $celdas): ValidacionDeFuncionario
    {
        $primeraFilaDelRut = [];
        $primeraFilaDelCorreo = [];

        return $this->validarFila(
            ValidacionDeFuncionario::SIN_FILA,
            array_map($this->texto(...), array_values($celdas)),
            $primeraFilaDelRut,
            $primeraFilaDelCorreo,
        );
    }

    public static function mensajeDeEncabezado(): string
    {
        return 'El encabezado de la planilla no corresponde. La fila 1 debe tener estas columnas, en este orden: '.implode(' | ', self::ENCABEZADO).'.';
    }

    /**
     * @param  list<string>  $celdas
     * @param  array<string, int>  $primeraFilaDelRut
     * @param  array<string, int>  $primeraFilaDelCorreo
     */
    private function validarFila(int $numeroDeFila, array $celdas, array &$primeraFilaDelRut, array &$primeraFilaDelCorreo): ValidacionDeFuncionario
    {
        /** @var array<string, list<string>> $errores */
        $errores = [];
        [$rutEscrito, $nombres, $primerApellido, $segundoApellido, $correoEscrito, $telefono, $funcion] = array_pad($celdas, count(self::ENCABEZADO), '');

        if (implode('', array_slice($celdas, count(self::ENCABEZADO))) !== '') {
            $errores[self::CAMPO_FILA][] = 'La fila tiene datos fuera de las '.count(self::ENCABEZADO).' columnas de la planilla.';
        }

        $rut = null;
        try {
            $rut = Rut::desde($rutEscrito);
            $primeraFila = $primeraFilaDelRut[$rut->paraAlmacenar()] ??= $numeroDeFila;
            if ($primeraFila !== $numeroDeFila) {
                $errores['rut'][] = "El RUT ya aparece en la fila {$primeraFila}.";
            }
        } catch (RutInvalido $e) {
            $errores['rut'][] = $e->getMessage();
        }

        $this->exigirTexto($nombres, 'Los nombres son obligatorios.', 'Los nombres no pueden superar los '.self::MAXIMO_DEL_NOMBRE.' caracteres.', $errores['nombres']);
        $this->exigirTexto($primerApellido, 'El primer apellido es obligatorio.', 'El primer apellido no puede superar los '.self::MAXIMO_DEL_NOMBRE.' caracteres.', $errores['primer_apellido']);

        if (mb_strlen($segundoApellido) > self::MAXIMO_DEL_NOMBRE) {
            $errores['segundo_apellido'][] = 'El segundo apellido no puede superar los '.self::MAXIMO_DEL_NOMBRE.' caracteres.';
        }

        $correo = $this->correo($correoEscrito, $errores['correo']);
        if ($correo !== null) {
            $primeraFila = $primeraFilaDelCorreo[$correo] ??= $numeroDeFila;
            if ($primeraFila !== $numeroDeFila) {
                $errores['correo'][] = "El correo ya aparece en la fila {$primeraFila}.";
            }
        }

        if (mb_strlen($telefono) > self::MAXIMO_DEL_TELEFONO) {
            $errores['telefono'][] = 'El teléfono no puede superar los '.self::MAXIMO_DEL_TELEFONO.' caracteres.';
        } elseif ($telefono !== '' && preg_match('/^[0-9 +\-()]+$/', $telefono) !== 1) {
            $errores['telefono'][] = 'El teléfono solo puede tener números, espacios y los signos + - ( ).';
        }

        $categoria = self::CATEGORIAS[$this->normalizar($funcion)] ?? null;
        if ($funcion === '') {
            $errores['funcion'][] = 'La función es obligatoria.';
        } elseif ($categoria === null) {
            $errores['funcion'][] = 'La función debe ser docente, directivo, asistente o profesional de apoyo.';
        }

        $errores = array_filter($errores);

        if ($errores !== [] || $rut === null || $correo === null || $categoria === null) {
            return new ValidacionDeFuncionario(null, $errores);
        }

        return new ValidacionDeFuncionario(new FilaDePlanilla(
            numeroDeFila: $numeroDeFila,
            rut: $rut,
            nombres: $nombres,
            primerApellido: $primerApellido,
            segundoApellido: $segundoApellido === '' ? null : $segundoApellido,
            correo: $correo,
            telefono: $telefono === '' ? null : $telefono,
            categoriaFuncion: $categoria,
        ), []);
    }

    /**
     * @param  list<string>|null  $errores
     */
    private function exigirTexto(string $valor, string $siFalta, string $siEsLargo, ?array &$errores): void
    {
        if ($valor === '') {
            $errores[] = $siFalta;
        } elseif (mb_strlen($valor) > self::MAXIMO_DEL_NOMBRE) {
            $errores[] = $siEsLargo;
        }
    }

    /**
     * Mismo criterio que ReglasDeRespuesta::correoParaEnviar de Vinkured (recortado y válido según FILTER_VALIDATE_EMAIL),
     * más minúsculas para detectar repetidos y guardar una sola forma.
     *
     * @param  list<string>|null  $errores
     */
    private function correo(string $correoEscrito, ?array &$errores): ?string
    {
        if ($correoEscrito === '') {
            $errores[] = 'El correo es obligatorio.';

            return null;
        }

        $correo = mb_strtolower($correoEscrito);

        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $errores[] = 'El correo no es válido.';

            return null;
        }

        return $correo;
    }

    /**
     * @param  list<string>  $celdas
     */
    private function esEncabezadoValido(array $celdas): bool
    {
        while ($celdas !== [] && end($celdas) === '') {
            array_pop($celdas);
        }

        return array_map($this->normalizar(...), $celdas) === array_map($this->normalizar(...), self::ENCABEZADO);
    }

    /** Minúsculas, sin tildes y con un solo espacio entre palabras, para comparar encabezados y funciones. */
    private function normalizar(string $texto): string
    {
        return (string) preg_replace('/\s+/', ' ', trim(Str::ascii(mb_strtolower($texto))));
    }

    /** Valor de la celda como texto recortado; Excel puede entregar el RUT como número. */
    private function texto(mixed $celda): string
    {
        $texto = match (true) {
            $celda === null => '',
            is_float($celda) && floor($celda) === $celda && abs($celda) < 1e15 => sprintf('%.0f', $celda),
            is_scalar($celda) => (string) $celda,
            $celda instanceof DateTimeInterface => $celda->format('Y-m-d'),
            $celda instanceof DateInterval => $celda->format('%H:%I:%S'),
            default => '',
        };

        return preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $texto) ?? '';
    }
}
