/**
 * Pasos de las guías (`useTour`) que comparten varias pantallas. Cada paso apunta a un `data-tour="…"`; los que no
 * existen o no se ven en ese momento se saltan (o muestran `siNoExiste`).
 */

/**
 * Consulta de registros de personas: Registro Criminal y Faltas y Contravenciones (mismo formulario y tarjeta).
 *
 * @param {string} modulo nombre del módulo, p. ej. «Registro Criminal»
 */
export const guiaConsultaRegistros = (modulo) => [
    {
        titulo: `Consultar ${modulo}`,
        texto: `Desde esta pantalla busca personas registradas en ${modulo}. Cada búsqueda nueva y cada detalle que abra quedan registrados en la Auditoría de consultas.`,
    },
    {
        elemento: '[data-tour="filtro"]',
        titulo: '1. Filtrar por',
        texto: 'Elija primero en qué dato buscar: C.I., nombre completo, apellidos, alias, nombre supuesto, celular, CUD, padre, madre, hijos, cónyuge o fecha de nacimiento (dd-mm-aaaa).',
    },
    { elemento: '[data-tour="texto"]', titulo: '2. Texto a buscar', texto: 'Escriba lo que busca: al menos 4 caracteres. Puede escribir sólo una parte del nombre.' },
    { elemento: '[data-tour="buscar"]', titulo: '3. Buscar', texto: 'Se habilita cuando eligió un filtro y escribió 4 caracteres o más. También puede presionar Enter.' },
    { elemento: '[data-tour="vista"]', titulo: 'Vista', texto: 'Muestra los resultados en cuadrícula o en lista. El sistema recuerda su elección.', lado: 'left' },
    {
        elemento: '[data-tour="resultados"] [data-tour="tarjeta"]',
        titulo: 'Resultados',
        texto: 'Cada tarjeta es un registro encontrado, con su número, la persona, el C.I., el alias, la especialidad y la fecha de registro. Al bajar se cargan más.',
        lado: 'top',
        siNoExiste: {
            titulo: 'Resultados',
            texto: 'Aquí aparecerán los registros encontrados. Haga una búsqueda y vuelva a abrir la guía para conocer qué puede hacer con cada resultado.',
        },
    },
    { elemento: '[data-tour="tarjeta-ver"]', titulo: 'Ver detalles', texto: 'Abre el detalle completo: fotos de frente y perfil, datos de la persona, datos del registro y la información adicional (otros registros, mandamientos, vehículos y teléfonos).' },
    { elemento: '[data-tour="tarjeta-foto"]', titulo: 'Fotos', texto: 'Haga clic en la foto para verla ampliada, con la foto de perfil.', lado: 'right' },
    { elemento: '[data-tour="ayuda"]', titulo: 'Guía', texto: 'Puede volver a ver esta guía cuando quiera con este botón.', lado: 'left' },
];

/*
 * Guías RESUMIDAS de los formularios de registro: un paso por bloque de campos (no uno por campo). Los elementos se
 * marcan con `data-tour="f-…"` en cada formulario; el botón Guardar lleva `data-tour="f-guardar"` (en el pie del modal
 * o de la página).
 */

const GUARDAR = {
    elemento: '[data-tour="f-guardar"]',
    titulo: 'Guardar',
    texto: 'Guarda los datos. Si falta algo obligatorio, el campo se marca en rojo con el motivo.',
    lado: 'top',
};

const introFormulario = (que) => ({
    titulo: `Formulario de ${que}`,
    texto: 'Un recorrido corto por las partes del formulario. Los campos con * son obligatorios; los demás pueden quedar vacíos.',
});

export const guiaFormularioMandamiento = () => [
    introFormulario('mandamiento'),
    { elemento: '[data-tour="f-documento"]', titulo: 'Documento', texto: 'Número de hoja de ruta y si el mandamiento es original o fotocopia.' },
    {
        elemento: '[data-tour="f-persona"]',
        titulo: 'Persona',
        texto: 'Busque a la persona por nombre o C.I. Si todavía no está registrada, use «+ Añadir» para registrarla sin salir del formulario.',
    },
    {
        elemento: '[data-tour="f-catalogos"]',
        titulo: 'Juzgado, delito y tipo',
        texto: 'Elija de la lista escribiendo para filtrar. Si la opción no existe, escríbala completa y elija «(añadir nuevo)».',
    },
    { elemento: '[data-tour="f-archivo"]', titulo: 'Archivo (opcional)', texto: 'Adjunte la foto o el PDF del mandamiento, hasta 5 MB.' },
    {
        elemento: '[data-tour="f-estado"]',
        titulo: 'Estado',
        texto: 'Escriba el estado o elija uno de la lista. Si contiene la palabra EJECUTADO, se piden la fecha, quién lo ejecutó y el acta.',
        lado: 'top',
    },
    GUARDAR,
];

export const guiaFormularioPersona = () => [
    introFormulario('persona'),
    { elemento: '[data-tour="f-nombres"]', titulo: 'Nombres y apellidos', texto: 'El nombre es obligatorio; se guarda en mayúsculas.' },
    {
        elemento: '[data-tour="f-ci"]',
        titulo: 'C.I. y complemento',
        texto: 'Al escribir el C.I. (y su complemento, si tiene) el sistema avisa si ya existe una persona con ese número, para no registrarla dos veces.',
    },
    {
        elemento: '[data-tour="f-datos"]',
        titulo: 'Demás datos',
        texto: 'Teléfono, alias, nacimiento, nacionalidad (boliviana por defecto), estado civil, familiares, domicilio y otros: complete los que conozca.',
    },
    { elemento: '[data-tour="f-fotos"]', titulo: 'Fotos (opcional)', texto: 'Arrastre o elija hasta 4 fotos JPG, PNG o WEBP de hasta 2 MB cada una.', lado: 'top' },
    { ...GUARDAR, texto: `${GUARDAR.texto} Si aparece «Guardar y Registrar Nuevo», deja el formulario limpio para cargar otra persona.` },
];

export const guiaFormularioTelefono = () => [
    introFormulario('teléfono'),
    { elemento: '[data-tour="f-numero"]', titulo: 'Número celular', texto: 'Obligatorio y no se puede repetir: un número ya registrado se rechaza. Agregue la empresa si la conoce.' },
    { elemento: '[data-tour="f-persona"]', titulo: 'Relacionar con persona (opcional)', texto: 'Busque a la persona dueña del número por nombre o C.I. También puede vincularla después desde el listado.' },
    { elemento: '[data-tour="f-extra"]', titulo: 'Información adicional', texto: 'Caso, respuesta del requerimiento y los datos de CallApp, TrueCall y UniNet, si los tiene.' },
    GUARDAR,
];

export const guiaFormularioImei = () => [
    introFormulario('IMEI'),
    { elemento: '[data-tour="f-imei"]', titulo: 'IMEI', texto: 'Obligatorio y sin espacios. Agregue las características del equipo si las conoce.' },
    {
        elemento: '[data-tour="f-telefonos"]',
        titulo: 'Teléfonos vinculados',
        texto: 'Escriba al menos 3 números o letras del teléfono o de su dueño y elíjalo. Puede vincular varios; la × quita uno.',
    },
    GUARDAR,
];

export const guiaFormularioVehiculo = () => [
    introFormulario('vehículo'),
    { elemento: '[data-tour="f-placa"]', titulo: 'Datos del vehículo', texto: 'La placa es obligatoria (sin espacios). Responsable, descripción y caso relacionado son opcionales.' },
    {
        elemento: '[data-tour="f-personas"]',
        titulo: 'Personas asociadas',
        texto: 'Busque a la persona, indique el tipo de información (RUAT, SOAT, BSISA…) y pulse «Agregar Persona». Puede agregar varias.',
    },
    { elemento: '[data-tour="f-fotos"]', titulo: 'Fotos (opcional)', texto: 'Hasta 10 fotos JPG, PNG o WEBP de hasta 2 MB cada una.', lado: 'top' },
    GUARDAR,
];

/**
 * Registro Criminal y Faltas y Contravenciones (mismo formulario).
 *
 * @param {string} modulo nombre del módulo, p. ej. «Registro Criminal»
 */
export const guiaFormularioRegistro = (modulo) => [
    { titulo: `Formulario de ${modulo}`, texto: 'Un recorrido corto por las partes del formulario. Los campos con * son obligatorios.' },
    { elemento: '[data-tour="f-fotos"]', titulo: 'Fotografías', texto: 'Haga clic en cada recuadro para elegir la foto de frente y la de perfil (hasta 10 MB).' },
    {
        elemento: '[data-tour="f-buscar-persona"]',
        titulo: 'Persona',
        texto: 'Si la persona ya está registrada, búsquela aquí y sus datos se completan solos. Si no existe, complete sus datos abajo y se registrará al guardar.',
    },
    {
        elemento: '[data-tour="f-ci"]',
        titulo: 'C.I. y complemento',
        texto: 'Al escribirlos, el sistema busca si la persona ya existe y ofrece «Usar esta persona» para no duplicarla.',
    },
    {
        elemento: '[data-tour="f-registro"]',
        titulo: 'Datos del registro',
        texto: 'Además de la fecha de registro y los nombres, son obligatorios el género y la división. El resto (especialidad, rasgos, modus operandi…) complételo si lo conoce.',
        lado: 'left',
    },
    {
        elemento: '[data-tour="f-guardar"]',
        titulo: 'Guardar',
        texto: 'Guarda el registro. Al registrar, «Guardar y Registrar Nuevo» deja el formulario limpio para cargar otro.',
        lado: 'top',
    },
];

export const guiaFormularioUsuario = () => [
    { titulo: 'Formulario de usuario', texto: 'Un recorrido corto por el formulario. Los campos con * son obligatorios.' },
    { elemento: '[data-tour="f-cuenta"]', titulo: 'Nombre y correo', texto: 'El correo es con el que la persona inicia sesión: no puede repetirse entre usuarios.' },
    {
        elemento: '[data-tour="f-rol"]',
        titulo: 'Rol',
        texto: 'Define qué pantallas y acciones verá el usuario. Los permisos de cada rol se configuran en Seguridad › Roles y Permisos.',
    },
    {
        elemento: '[data-tour="f-clave"]',
        titulo: 'Contraseña',
        texto: 'Mínimo 6 caracteres, escrita dos veces igual. Al editar, déjela vacía para mantener la actual. El ojo muestra lo escrito.',
    },
    GUARDAR,
];

export const guiaFormularioRol = () => [
    { titulo: 'Formulario de rol', texto: 'Un recorrido corto: nombre del rol, de dónde copiar permisos y cómo marcarlos.' },
    {
        elemento: '[data-tour="f-rol-datos"]',
        titulo: 'Nombre y descripción',
        texto: 'El nombre se guarda en minúsculas con guion bajo (p. ej. analista_daci). La descripción es lo que el usuario ve junto a su nombre en la cabecera.',
    },
    {
        elemento: '[data-tour="f-copiar"]',
        titulo: 'Copiar permisos de otro rol',
        texto: 'Para no empezar de cero: «Reemplazar» deja exactamente los permisos del rol elegido y «Sumar» los agrega a los marcados. El rol de origen no cambia.',
    },
    {
        elemento: '[data-tour="f-permisos"]',
        titulo: 'Permisos por módulo',
        texto: 'Marque un módulo completo con su casilla principal o permiso por permiso. «Marcar todos» y «Quitar todos» actúan sobre todo el formulario.',
        lado: 'top',
    },
    { ...GUARDAR, texto: 'Guarda el rol. Los usuarios que lo tengan ven los cambios al recargar la página.' },
];

