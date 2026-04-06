# Fixxa - Plataforma de Gestión de Servicios S.A.S.

Fixxa es una plataforma robusta diseñada para conectar clientes con técnicos especializados para la resolución de casos de servicio técnico.

## 🚀 Funcionalidades Principales

- **Gestión Administrativa Completa**:
  - CRUD de Clientes y Técnicos.
  - Bloqueo/Desbloqueo de usuarios (Restricción de login para usuarios bloqueados).
  - Gestión de otros administradores (Exclusivo para Super Admin).
- **Sistema de Permisos Granulares**:
  - Roles: `super_admin`, `admin`, `moderator`, `client`, `technician`.
  - Control de acceso basado en roles para todas las rutas de la API.
- **Perfiles Personalizados**:
  - Cada rol cuenta con su propio controlador de perfil y gestión de información personal.
- **Sistema de Casos de Servicio**:
  - Los clientes pueden crear casos con múltiples imágenes.
  - Los técnicos pueden ver casos disponibles, responder con presupuestos y dudas.
- **Chat en Tiempo Real**:
  - Sistema de mensajería privada entre cliente y técnico para discutir casos de servicio.
  - Notificaciones en tiempo real integradas con Laravel Reverb.

## 🛠 Requisitos

- **PHP** >= 8.2
- **Composer**
- **Node.js** & **NPM**
- **SQLite** (por defecto) o MySQL.

## 📦 Instalación y Configuración

1. **Clonar el repositorio** y entrar a la carpeta:
```bash
cd fixxa
```

2. **Instalar dependencias de PHP**:
```bash
composer install
```

3. **Configurar el entorno**:
```bash
cp .env.example .env
php artisan key:generate
```
*Asegúrate de que `DB_CONNECTION` esté configurado (por defecto usa `database.sqlite`).*

4. **Ejecutar Migraciones y Seeders**:
Este paso es crucial para crear las tablas (incluyendo `status`, `conversations`, `messages`) y los roles iniciales.
```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
```
*Esto creará los roles y asignará `super_admin` al primer usuario del sistema.*

5. **Configurar Laravel Reverb (Chat en Tiempo Real)**:
Si aún no está instalado, ejecuta el comando de instalación de broadcasting:
```bash
php artisan install:broadcasting
```
Este comando instalará Reverb y creará los archivos de configuración necesarios.

6. **Instalar dependencias de Frontend**:
```bash
npm install
```

## 🏁 Ejecución

Para poner en marcha el sistema con todas sus funcionalidades:

1. **Servidor de API**:
```bash
php artisan serve
```

2. **Servidor de Sockets (Reverb)**:
```bash
php artisan reverb:start
```

3. **Compilación de Assets**:
```bash
npm run dev
```

## 🔐 Credenciales Iniciales
Después de ejecutar el `RolePermissionSeeder`, el primer usuario en tu base de datos tendrá el rol de `super_admin`. Puedes usar ese usuario para gestionar otros administradores, clientes y técnicos.

---
© 2026 Fixxa S.A.S. - Desarrollado con Laravel 12.
