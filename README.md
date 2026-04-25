# KYZ Logistic

KYZ Logistic es una plataforma para planificar recorridos y organizar jornadas operativas de forma mas eficiente, reduciendo tiempos de coordinacion, errores manuales y costo operativo en equipos de campo. La aplicacion combina gestion de domicilios, asignaciones y hojas de ruta en una experiencia unificada para acelerar la toma de decisiones logisticas.

## Que resuelve para el mercado

- Digitaliza la planificacion de recorridos para operaciones con alto volumen de visitas.
- Reduce trabajo operativo manual con flujos de asignacion y seguimiento estructurados.
- Mejora trazabilidad y consistencia de datos entre equipos administrativos y de campo.
- Permite escalar la operacion con una base de datos centralizada y frontend web.

## Stack tecnologico

- **Frontend:** React + Vite
- **Backend:** PHP 8.2 (Apache)
- **Base de datos:** MySQL 8
- **Orquestacion local:** Docker Compose

## Arquitectura del repositorio

- `frontend/`: aplicacion SPA (Vite + React)
- `backend/`: API y logica de negocio en PHP
- `database/`: esquema, seed y migraciones SQL
- `docker-compose.yml`: entorno local completo

## Get Started para desarrolladores

### 1) Prerrequisitos

- Docker Desktop instalado y en ejecucion.
- Git instalado.

### 2) Clonar repositorio

```bash
git clone https://github.com/h-kANAME/kyz_logistic.git
cd kyz_logistic
```

### 3) Configurar variables de entorno

Backend:

```bash
cp backend/.env.example backend/.env
```

Frontend (si aplica para tu entorno local):

```bash
cp frontend/.env.example frontend/.env.development
```

> Importante: no commitear archivos `.env` con credenciales reales.

### 4) Levantar entorno local

Desde la raiz del repo:

```bash
docker compose up -d --build
docker compose ps
```

Servicios esperados:

- Frontend: `http://localhost:5173`
- Backend: `http://localhost:8080`
- MySQL: `localhost:3306`

### 5) Base de datos local

En entorno Docker, MySQL inicializa automaticamente con:

- `database/schema.sql`
- `database/seed.sql`

Si necesitas aplicar migraciones incrementales manualmente, revisar:

- `database/DEPLOY_SQL_ORDER.txt`

### 6) Flujo de desarrollo recomendado

- Editar codigo en `frontend/` y `backend/`.
- Verificar contenedores con `docker compose ps`.
- Reiniciar stack al cambiar configuraciones relevantes:

```bash
docker compose up -d --build
```

### 7) Build de frontend para produccion

El build de produccion del frontend se genera **siempre con Docker**:

```bash
docker compose --profile build run --rm frontend_build
```

Esto genera el artefacto en `frontend/dist/`.

## Consideraciones de versionado

- No subir credenciales ni `.env` reales.
- No subir datos operativos locales ni backups SQL.
- El repositorio ya contempla exclusiones en `.gitignore` para artefactos locales y archivos sensibles.

## Licencia

Proyecto bajo licencia **GPL-3.0**.
