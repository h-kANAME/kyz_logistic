const RAW_BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080';
const API_BASE = RAW_BASE.replace(/\/$/, '');

const defaultHeaders = {
  Accept: 'application/json',
};

async function request(path, { method = 'GET', token, body, isMultipart = false } = {}) {
  const headers = {
    ...defaultHeaders,
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const config = { method, headers };

  if (body !== undefined) {
    if (isMultipart) {
      config.body = body;
      delete headers['Content-Type'];
    } else {
      headers['Content-Type'] = 'application/json';
      config.body = JSON.stringify(body);
    }
  }

  const response = await fetch(`${API_BASE}${path}`, config);
  let payload;

  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok || !payload?.success) {
    const message = payload?.message || 'Error de comunicacion con la API';
    throw new Error(message);
  }

  return payload.data;
}

export const api = {
  login: (email, password) => request('/api/auth/login', { method: 'POST', body: { email, password } }),
  me: (token) => request('/api/auth/me', { token }),

  getUsuarios: (token, rol) => request(`/api/usuarios${rol ? `?rol=${encodeURIComponent(rol)}` : ''}`, { token }),
  getUsuarioById: (token, userId) => request(`/api/usuarios/${userId}`, { token }),
  createUsuario: (token, payload) => request('/api/usuarios', { method: 'POST', token, body: payload }),
  updateUsuario: (token, userId, payload) => request(`/api/usuarios/${userId}`, { method: 'PUT', token, body: payload }),
  deactivateUsuario: (token, userId) => request(`/api/usuarios/${userId}`, { method: 'DELETE', token }),
  updateUsuarioPassword: (token, userId, password) => request(`/api/usuarios/${userId}/password`, { method: 'PATCH', token, body: { password } }),

  getSecciones: (token) => request('/api/secciones', { token }),
  getSeccionById: (token, seccionId) => request(`/api/secciones/${seccionId}`, { token }),
  updateSeccion: (token, seccionId, descripcion) => request(`/api/secciones/${seccionId}`, { method: 'PUT', token, body: { descripcion } }),
  getDomicilios: (token, query = '') => request(`/api/domicilios${query ? `?${query}` : ''}`, { token }),
  getDomicilioById: (token, domicilioId) => request(`/api/domicilios/${domicilioId}`, { token }),

  getJornadas: (token, query = '') => request(`/api/jornadas${query ? `?${query}` : ''}`, { token }),
  getJornadaById: (token, jornadaId) => request(`/api/jornadas/${jornadaId}`, { token }),
  createJornada: (token, payload) => request('/api/jornadas', { method: 'POST', token, body: payload }),
  updateJornada: (token, jornadaId, payload) => request(`/api/jornadas/${jornadaId}`, { method: 'PUT', token, body: payload }),
  deleteJornada: (token, jornadaId) => request(`/api/jornadas/${jornadaId}`, { method: 'DELETE', token }),
  updateJornadaEstado: (token, jornadaId, estado) => request(`/api/jornadas/${jornadaId}/estado`, { method: 'PATCH', token, body: { estado } }),
  getJornadaAsignaciones: (token, jornadaId) => request(`/api/jornadas/${jornadaId}/asignaciones`, { token }),
  getJornadaAsignacionesPaginadas: (token, jornadaId, query = '') => request(`/api/jornadas/${jornadaId}/asignaciones/paginadas${query ? `?${query}` : ''}`, { token }),
  generarRuta: (token, jornadaId, payload) => request(`/api/jornadas/${jornadaId}/asignaciones`, { method: 'POST', token, body: payload }),
  planificarJornadaDia: (token, jornadaId, payload) => request(`/api/jornadas/${jornadaId}/plan-dia`, { method: 'POST', token, body: payload }),
  guardarHojaRuta: (token, jornadaId, payload) => request(`/api/jornadas/${jornadaId}/hojas-ruta`, { method: 'POST', token, body: payload }),
  getHojasRuta: (token, query = '') => request(`/api/hojas-ruta${query ? `?${query}` : ''}`, { token }),
  getHojaRutaById: (token, hojaId) => request(`/api/hojas-ruta/${hojaId}`, { token }),
  updateHojaRuta: (token, hojaId, payload) => request(`/api/hojas-ruta/${hojaId}`, { method: 'PUT', token, body: payload }),
  deleteHojaRuta: (token, hojaId) => request(`/api/hojas-ruta/${hojaId}`, { method: 'DELETE', token }),
  openNextBatchHojaRuta: (token, hojaId, payload = {}) => request(`/api/hojas-ruta/${hojaId}/open-next-batch`, { method: 'POST', token, body: payload }),
  registrarVisitaHojaRuta: (token, hojaId, payload) => request(`/api/hojas-ruta/${hojaId}/visitas`, { method: 'POST', token, body: payload }),
  getHistorialDomicilio: (token, domicilioId) => request(`/api/hojas-ruta/historial/domicilio/${domicilioId}`, { token }),
  priorizarJornada: (token, jornadaId) => request(`/api/llm/jornadas/${jornadaId}/priorizar`, { method: 'POST', token }),

  getConsultorPerfil: (token) => request('/api/consultor/perfil', { token }),
  updateConsultorPerfil: (token, payload) => request('/api/consultor/perfil', { method: 'PUT', token, body: payload }),

  getAsignacionById: (token, asignacionId) => request(`/api/asignaciones/${asignacionId}`, { token }),
  updateAsignacion: (token, asignacionId, payload) => request(`/api/asignaciones/${asignacionId}`, { method: 'PATCH', token, body: payload }),
  getLotes: (token, query = '') => request(`/api/lotes${query ? `?${query}` : ''}`, { token }),
  createLote: (token, payload) => request('/api/lotes', { method: 'POST', token, body: payload }),
  setLoteDomicilios: (token, loteId, payload) => request(`/api/lotes/${loteId}/domicilios`, { method: 'PUT', token, body: payload }),
  bootstrapLoteFromDomicilios: (token, loteId) => request(`/api/lotes/${loteId}/bootstrap-domicilios`, { method: 'POST', token, body: {} }),
  assignLoteToConsultor: (token, loteId, payload) => request(`/api/lotes/${loteId}/asignar`, { method: 'POST', token, body: payload }),
  getConsultorLotes: (token, query = '') => request(`/api/consultor/lotes${query ? `?${query}` : ''}`, { token }),
  planificarLoteDia: (token, consultorLoteId, payload) => request(`/api/consultor/lotes/${consultorLoteId}/plan-dia`, { method: 'POST', token, body: payload }),
  guardarHojaRutaDesdeLote: (token, consultorLoteId, payload) => request(`/api/consultor/lotes/${consultorLoteId}/hojas-ruta`, { method: 'POST', token, body: payload }),
  getGeocodingEstado: (token, loteId = null) => request(`/api/geocoding/domicilios/estado${loteId ? `?lote_id=${encodeURIComponent(loteId)}` : ''}`, { token }),
  geocodingReset: (token, payload) => request('/api/geocoding/domicilios/reset', { method: 'POST', token, body: payload }),
  geocodingWizardQueue: (token, query = '') => request(`/api/geocoding/domicilios/wizard${query ? `?${query}` : ''}`, { token }),
  geocodingWizardBulkPropose: (token, payload, provider = 'nominatim') => {
    const providerSafe = provider === 'google' ? 'google' : 'nominatim';
    return request('/api/geocoding/domicilios/wizard/bulk-propose', { method: 'POST', token, body: { ...payload, provider: providerSafe } });
  },
  geocodingWizardBulkSave: (token, payload) => request('/api/geocoding/domicilios/wizard/bulk-save', { method: 'POST', token, body: payload }),
  geocodingWizardAttempt: (token, domicilioId, payload) => request(`/api/geocoding/domicilios/wizard/${domicilioId}/attempt`, { method: 'POST', token, body: payload }),
  geocodingWizardManual: (token, domicilioId, payload) => request(`/api/geocoding/domicilios/wizard/${domicilioId}/manual`, { method: 'POST', token, body: payload }),
  geocodingWizardFallback: (token, domicilioId) => request(`/api/geocoding/domicilios/wizard/${domicilioId}/fallback`, { method: 'POST', token, body: {} }),

  importDomicilios: (token, file, sheet = 0, loteId = null) => {
    const form = new FormData();
    form.append('file', file);
    const query = [`sheet=${sheet}`];
    if (loteId) {
      query.push(`lote_id=${encodeURIComponent(loteId)}`);
    }
    return request(`/api/import/domicilios?${query.join('&')}`, {
      method: 'POST',
      token,
      body: form,
      isMultipart: true,
    });
  },

  exportDomiciliosXlsx: async (token) => {
    const response = await fetch(`${API_BASE}/api/export/domicilios/xlsx`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      },
    });
    if (!response.ok) {
      let message = 'Error al exportar domicilios';
      try {
        const payload = await response.json();
        if (payload?.message) {
          message = payload.message;
        }
      } catch {
        /* respuesta no JSON */
      }
      throw new Error(message);
    }
    return response.blob();
  },
};
