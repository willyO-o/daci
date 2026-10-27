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
