{{-- Correo de TEXTO PLANO: nada de HTML. Se imprime sin escapar (`{!! !!}`) porque escapar corrompería el texto
     ("&" -> "&amp;"); no hay HTML donde pueda interpretarse. Igual para cuentas nuevas y existentes (ADR-0005). --}}Estimado(a):

{!! $establecimiento !!} le invita a usar VINKURED, la plataforma del establecimiento.

Para aceptar la invitación, abra este enlace:

{!! $enlace !!}

El enlace sirve una sola vez y vence el {!! $venceEl !!}. Al abrirlo se le pedirá crear su contraseña o, si usted ya tiene una cuenta en VINKURED, iniciar sesión con ella.

Si usted no esperaba esta invitación, puede ignorar este mensaje.

VINKURED
