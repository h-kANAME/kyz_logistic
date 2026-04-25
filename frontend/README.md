# Frontend KYZ Logistica

Frontend React para el sistema de ruteo de cobranzas domiciliarias.

## Proposito funcional validado

El sistema soporta tres perfiles operativos:

- Admin: gestiona usuarios, datos base e importaciones de domicilios
- Supervisor: crea jornadas, genera rutas y monitorea asignaciones
- Consultor: ejecuta su hoja de ruta diaria y registra resultados de visita

Objetivo de negocio: reducir tiempo muerto entre visitas y mejorar la trazabilidad operativa de la cobranza domiciliaria en Santa Fe.

## Stack

- React 18 + Vite
- Material UI (MUI)
- React Router
- Fetch API para integracion con backend PHP

## Sistema visual

La identidad visual del frontend esta centralizada en [frontend/src/theme.js](frontend/src/theme.js).

- Nombre de paleta: Terracota Operativa
- Tokens semanticos centralizados: brand, surfaces, borders, effects, states y shape
- Componentes base gobernados desde el tema: AppBar, Card, Button, Chip, TextField y OutlinedInput
- Selector de tema en NavBar: Estandar y Boquita

### Temas disponibles

- Estandar: paleta Terracota Operativa (tema claro)
- Boquita: paleta inspirada en Boca Juniors (azul y amarillo) con modo oscuro incorporado

### Regla de aprobacion de cambios de estilo

- Nivel 1: cambios de contenido o layout menor. No requieren aprobacion visual especial.
- Nivel 2: cambios de composicion o uso de variantes existentes. Requieren revision rapida.
- Nivel 3: cambios de tokens, gradientes, tipografia, radios, sombras o identidad. Requieren aprobacion explicita.

### Regla de implementacion

- Evitar colores y sombras inline en pantallas.
- Preferir tokens del tema antes que valores hardcoded.
- Si aparece una nueva necesidad visual recurrente, se resuelve en el tema o en un componente base, no pantalla por pantalla.

## Variables de entorno

Copiar valores en un archivo .env dentro de frontend si necesitas override:

VITE_API_BASE_URL=http://localhost:8080

## Uso con Docker Compose

Desde la raiz del proyecto:

1. Levantar servicios:

   docker compose up -d db backend frontend

2. Ver logs del frontend:

   docker compose logs -f frontend

3. Abrir aplicacion:

   http://localhost:5173

## Credenciales iniciales

Si se uso el seed actual:

- Email: admin@kyz.local
- Contrasena: Admin1234!

## Pantallas implementadas

- Login
- Dashboard Admin
- Dashboard Supervisor
- Dashboard Consultor

Cada dashboard consume endpoints reales del backend y muestra acciones principales por rol.
