<!-- @host-owned Copied from Vinkured docs/diseno.md — do not modify here; replace with the host class at integration. -->
# Brief de diseño de VINKURED

**Estado: versión provisional (2026-10-16).** Parte de una dirección elegida en conversación y se irá ajustando con el uso. Las skills de UI (`.agents/skills/`) leen este documento como única fuente de tokens: lo que no esté aquí es `needs_brief`, no se improvisa.

## Decisiones y su estado

| Decisión | Estado |
|---|---|
| Usuarios objetivo: Coordinadores y Administradores de establecimiento, uso diario | Firme |
| Dirección visual "Mesa de trabajo" con el acento verde azulado de "Cuaderno sereno" | Provisional |
| Apoderado: flujo liviano y responsivo (enviar alerta y ver su estado), mismos tokens, sin marca aparte | Provisional |
| Modo oscuro: tokens definidos; activarlo en el hito 1 o después | Pendiente |
| Modo oscuro: tokens incompletos; faltan `borde`, `acento-hover`, `foco`, par `neutro`, botón de peligro y `velo`; no activar hasta definirlos | Pendiente |
| Logo y wordmark | Pendiente |
| Marca propia por colegio (logo y color) | Pendiente |
| Tuteo o usted en comunicaciones a apoderados | Pendiente (hoy: usted, como en los anexos del protocolo) |
| Tipografía autoalojada (sin CDN de fuentes: datos de menores) | Pendiente: ADR en la primera pantalla |
| Set de íconos | Pendiente: ADR. Mientras tanto, solo SVG en línea escritos a mano o texto |

## Identidad

- Nombre: VINKURED.
- Personalidad: mesa de trabajo serena. Sobria, clara y confiable; transmite orden y reserva. Sin mascotas, ilustraciones lúdicas ni estadísticas animadas.
- Para funcionarios: herramienta eficiente que no cansa tras horas de uso. Para apoderados: sencilla y tranquilizadora, sin presión.
- El color de marca se reserva para acción, selección y foco. El resto es neutro; el color fuerte comunica estado.

## Paleta (contraste verificado WCAG 2.2 AA, 2026-10-16)

Texto normal exige 4.5:1; bordes de campo, anillos de foco y componentes 3:1.

### Modo claro

| Token | Valor | Uso | Contraste |
|---|---|---|---|
| `fondo` | `#F8FAFC` | Fondo de página | texto 17.06 |
| `superficie` | `#FFFFFF` | Tarjetas, paneles, campos | texto 17.85 |
| `superficie-atenuada` | `#F1F5F9` | Encabezado de tabla, bloques secundarios | texto 16.30 |
| `texto` | `#0F172A` | Texto principal | ver arriba |
| `texto-atenuado` | `#475569` | Ayudas, metadatos | 7.24 sobre fondo, 6.92 sobre superficie atenuada |
| `acento` | `#0F5C63` | Botón primario, enlaces, selección | 7.69 sobre superficie, 7.35 sobre fondo; texto blanco sobre acento 7.69 |
| `acento-hover` | `#0B4A50` | Hover del botón primario | blanco sobre hover 9.95 |
| `acento-sutil` | `#E3F0F1` | Fila o ítem seleccionado | acento 6.59, texto 15.30 |
| `borde` | `#E2E8F0` | Separadores decorativos (no llevan información) | n/a |
| `velo` | `#0F172A` al 40 % de opacidad | Capa tras el cajón móvil | n/a |
| `borde-campo` | `#64748B` | Borde de campos y controles | 4.76 sobre superficie, 4.55 sobre fondo |
| `foco` | `#0F5C63` | Anillo de foco, 2 px con 2 px de separación | 7.69 |

### Estados (siempre con texto, nunca solo color)

| Estado | Texto | Fondo | Contraste |
|---|---|---|---|
| Éxito | `#166534` | `#DCFCE7` | 6.49 |
| Aviso | `#92400E` | `#FEF3C7` | 6.37 |
| Peligro | `#B91C1C` | `#FEE2E2` | 5.30 |
| Información | `#1D4ED8` | `#DBEAFE` | 5.49 |
| Neutro | `#334155` | `#E2E8F0` | 8.40 |

### Modo oscuro (provisional)

| Token | Valor | Contraste |
|---|---|---|
| `fondo` | `#0B1220` | texto 15.50 |
| `superficie` | `#111A2B` | texto 14.41; texto atenuado 8.31 |
| `superficie-atenuada` | `#172236` | texto atenuado 7.61 |
| `texto` / `texto-atenuado` | `#E5EAF2` / `#A9B4C6` | ver arriba |
| `acento` | `#5EC4CC` | 9.13 sobre fondo, 8.49 sobre superficie; texto `#0B1220` sobre acento 9.13 |
| `acento-sutil` | `#0F2A30` | acento 7.35 |
| `borde-campo` | `#6B7A90` | 3.99 sobre superficie |
| Éxito / Aviso / Peligro / Información | `#86EFAC` sobre `#123524`, `#FCD34D` sobre `#3A2A0A`, `#FCA5A5` sobre `#3B1215`, `#93C5FD` sobre `#14233F` | 9.58 / 9.61 / 8.62 / 8.68 |

### Escala z-index

Fija: contenido 0, elementos fijos de página 10, menús desplegables 20, modales 40, avisos emergentes 50.

## Tipografía

- Familia: Inter, con respaldo `ui-sans-serif, system-ui, sans-serif`. Una sola familia.
- Números tabulares (`tabular-nums`) en plazos, folios (`AAAA-NNNN`), fechas y columnas numéricas.
- Escala: 12 px (notas), 14 px (tablas y navegación), 16 px (texto base y campos), 18 px (subtítulos), 24 px (título de página), 30 px (título de portada). Peso 400 para texto y 600 para títulos y etiquetas.
- Los campos usan 16 px para evitar el zoom automático en móviles.
- Impresión y PDF (actas e informes): misma familia; el diseño de documento se define aparte.

## Espaciado y radios

- Base de 4 px. Pasos permitidos: 4, 8, 12, 16, 24, 32, 48 y 64 px.
- Radios: 6 px en botones y campos, 8 px en tarjetas y paneles, 12 px en modales, círculo completo en insignias.
- Jerarquía por bordes de 1 px más que por sombras. Una sola sombra, para menús y modales.

## Estructura y componentes base

- Disposición: barra lateral de 256 px, colapsable a 64 px; barra superior de 56 px con el selector de establecimiento. En móvil, la barra lateral es un cajón.
- Contenido: tablas a ancho completo; formularios hasta 768 px de ancho.
- **Botón:** primario (acento), secundario (borde), peligro y fantasma. Altura 40 px en escritorio y 44 px en pantallas táctiles. Peligro: borde y texto `peligro`; al pasar el cursor se rellena con `peligro` y el texto pasa a `sobre-acento`. Controles deshabilitados: 60 % de opacidad y sin efecto al pasar el cursor.
- **Campo:** etiqueta arriba (14 px, peso 600), error debajo en texto, nunca solo color; la obligatoriedad se escribe "(obligatorio)". Componente base `campo` (`resources/views/components/campo.blade.php`): etiqueta arriba, ayuda opcional, error debajo asociado con `aria-describedby`, `aria-invalid` solo cuando hay error y "(obligatorio)" en texto cuando es requerido. *(Adición provisional aprobada por el usuario.)*
- **Pantallas sin barra lateral** (inicio de sesión, elección de establecimiento): contenido centrado, tarjeta de hasta 448 px de ancho sobre `fondo` y un solo `h1`. *(Adición provisional aprobada por el usuario.)*
- **Mensajes y estados:** los errores de formulario y de campo usan el estado Peligro; los avisos informativos (sin acceso activo, bloqueo temporal) usan el estado Aviso; el éxito usa Éxito. Siempre con texto, nunca solo color. *(Adición provisional aprobada por el usuario.)*
- **Tabla:** filas de 40 px, encabezado fijo, texto de 14 px, sin cebrado; filtros arriba y filtros guardados; acciones masivas.
- **Insignia de estado:** icono (cuando exista el set) o texto, más la paleta de estados. Plazos: "En plazo", "Vence hoy", "Vencido", siempre con la cantidad en días hábiles.
- **Mapeo de insignias (adición provisional aprobada por el usuario):** variante de cada insignia del panel. Siempre con texto.

  | Insignia | Variante |
  |---|---|
  | En plazo (N días hábiles), también en invitaciones vigentes | Neutro |
  | Vence hoy | Aviso |
  | Vencido (plazos) y Vencida (invitaciones) | Peligro |
  | Escalada al Coordinador | Aviso |
  | Con el profesor jefe | Neutro |
  | Estado del caso (Abierto, En seguimiento) | Neutro |
  | Suspendida | Neutro |
  | Plazo no disponible | Neutro |

  Estados de alertas y avisos (listados y detalles; adición provisional aprobada por el usuario). Siempre con el texto del estado:

  | Insignia | Variante |
  |---|---|
  | Alerta Enviada, Respondida | Neutro |
  | Alerta Completada | Éxito |
  | Alerta Sin respuesta | Peligro |
  | Aviso Recibido | Aviso |
  | Aviso Formalizado | Éxito |
  | Aviso Descartado | Neutro |

  Notificación al apoderado en el detalle de la alerta (adición provisional aprobada por el usuario). Siempre con el texto de la insignia:

  | Insignia | Variante |
  |---|---|
  | Apoderado notificado (alerta Completada) | Éxito |
  | Notificación pendiente (alerta Respondida) | Aviso |
- **Línea de tiempo del caso:** vertical, con fecha, responsable y acción; es el componente central.
- **Modal de confirmación:** accesible (foco atrapado, Escape cierra); las acciones irreversibles sobre datos de menores piden confirmación explícita. Ancho de 448 px (`max-w-md`), igual que la tarjeta de las pantallas sin barra lateral. La única sombra, la de menús y modales, es `shadow-lg` de Tailwind. *(Adición provisional aprobada por el usuario.)*
- **Aviso emergente:** informa sin bloquear; los errores importantes se muestran en la propia pantalla.
- **Navegación:** lateral con elemento activo en `acento-sutil`.

## Tono y copy (español de Chile)

- Interfaz interna: acciones en infinitivo o sustantivo ("Guardar", "Crear caso", "Derivar"), sin tutear ni usar usted.
- Comunicaciones a apoderados: usted, tono formal y cercano, como en los anexos del protocolo.
- Términos de `CONTEXT.md`: Coordinador de Convivencia, Aviso, Alerta preventiva, Derivación, Caso. Nunca "Encargado" ni "Denunciado".
- Formatos: fecha `dd-mm-aaaa`, hora de 24 horas, números `1.234,5`.
- Mensajes concretos y sin culpar: "El RUN no es válido." en lugar de "Error".
- Textos del profesor jefe *(adición provisional aprobada por el usuario)*:
  - "Alertas de mi curso": `h1` y título del documento de la lista de alertas.
  - "Alertas preventivas enviadas por los apoderados del curso en jefatura.": descripción de esa lista.
  - "Alertas de mi curso por responder": bloque del panel.
  - "No hay alertas de mi curso por responder.": estado vacío.

## Densidad

- Escritorio: densidad media-alta en tablas, con 40 px por fila y celdas de 12 px de relleno horizontal.
- Formularios: una columna hasta 1024 px y dos columnas desde ahí. Los formularios largos (acta, informe) llevan borrador, indicador de guardado y secciones colapsables.

## Estados vacíos, de error y de carga

- **Vacío:** una frase y una acción clara, p. ej. "Aún no hay casos abiertos." con el botón "Crear caso".
- **Error de campo:** junto al campo. **Error de página:** título claro, qué pasó y qué hacer, sin detalles técnicos.
- **Carga:** esqueleto en tablas; botón con "Guardando…" y deshabilitado mientras dura la acción.

## Excepciones a las reglas por defecto de `baseline-ui`

- Sin degradados ni efectos de brillo.
- Animación solo de `transform` y `opacity`, de 150 ms, y respetando `prefers-reduced-motion`.

## Valores por defecto permitidos

- Puntos de quiebre: valores por defecto de Tailwind.
- Escala z-index: los pasos de Tailwind indicados arriba.
- Línea base de interlineado: la que trae cada tamaño de texto de Tailwind.
- Sombra: la de menús y modales de Tailwind, solo para superposiciones.

Todo lo demás (color, tipografía, espaciado, radios) debe salir de este documento. Si falta un token, el worker devuelve `needs_brief`.

## Referencias de inspiración

Solo patrones; no se copia ningún logo, combinación de colores, ilustración, texto ni diseño.

- Lirmi (lirmi.com/cl) y Edumokia: contexto del sector chileno. VINKURED se aleja de su violeta y de su tono promocional.
- CPOMS y MyConcern: cronología del caso y registros de preocupaciones.
- Panorama y Toddle: páginas explícitas de seguridad y privacidad.
- ClassDojo: contraejemplo para casos sensibles (estética lúdica).
