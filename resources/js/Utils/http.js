import axios from 'axios';

/**
 * Cliente para las peticiones JSON (buscadores, detalles en modal, altas al vuelo). Es una instancia propia para no
 * tocar la configuración que usa Inertia. Laravel la reconoce como AJAX y axios envía la cookie XSRF-TOKEN.
 */
export const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
    withXSRFToken: true,
});

export const esCancelacion = axios.isCancel;
