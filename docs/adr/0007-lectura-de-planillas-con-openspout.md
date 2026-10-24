# Lectura de planillas con OpenSpout

Estado: Propuesta (para que el equipo de VINKURED la acepte o la rechace).

El Administrador carga a los funcionarios de a uno o de forma masiva con una planilla (Q18), y VINKURED no trae ninguna biblioteca para leer planillas. Se propone leer los archivos `.xlsx` con `openspout/openspout` v5 (licencia MIT), que recorre la primera hoja fila por fila en streaming, sin cargar el archivo completo en memoria. Solo necesita las extensiones `dom`, `xmlreader` y `zip` de PHP, que ya están en el servidor. La biblioteca queda detrás de una interfaz propia (`LectorDePlanilla`), de modo que el resto del dominio no depende de ella y se puede reemplazar sin tocar la validación.

## Considered Options

- `phpoffice/phpspreadsheet`: lee y escribe casi cualquier formato, con fórmulas y estilos, pero arma la hoja completa en memoria; una planilla de 2.000 filas consume varias veces lo que consume OpenSpout, y la carga masiva no necesita fórmulas ni estilos.
- `maatwebsite/excel`: cómodo dentro de Laravel, pero envuelve a PhpSpreadsheet (hereda su consumo de memoria) y acopla la importación al framework con sus propias clases de importación y colas.
- Solo CSV, sin biblioteca: no añade dependencias, pero obliga al personal de los Establecimientos a exportar a CSV desde Excel, con problemas de separador (coma o punto y coma), codificación de tildes y RUT convertidos en números; es fricción y errores evitables para usuarios no técnicos.

## Consequences

Se añade una dependencia de Composer en tiempo de ejecución (`openspout/openspout`, sin dependencias propias más allá de las extensiones nombradas). Solo se aceptan archivos `.xlsx`: un `.xls` antiguo, un archivo protegido con contraseña o uno dañado se rechazan con un mensaje claro para el personal, sin detalles técnicos. Las filas se entregan numeradas como en Excel, incluidas las vacías, para que los errores por fila apunten a la fila que el Administrador ve. El tope de 2.000 funcionarios por archivo y las reglas de cada columna viven en el validador de la planilla, no en la biblioteca. Si más adelante se quisiera exportar planillas, la misma biblioteca también escribe `.xlsx`.
