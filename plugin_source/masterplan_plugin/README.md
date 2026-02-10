# MasterPlan 3D Pro

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.8+-green.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)

Plugin profesional de WordPress para visualización 3D de terrenos inmobiliarios con sistema de gestión de lotes y generación de leads.

## 🎯 Características Principales

### 🗺️ Mapa 3D Interactivo
- **Terreno con Relieve**: Visualización 3D del terreno usando MapLibre GL JS con Maptiler
- **Navegación Completa**: Rotación, inclinación (pitch), zoom compatible con touch y escritorio
- **Vista Satelital**: Integración con imagenación satelital de alta calidad

### 🎨 Editor "No-Code" de Lotes
- **Dibujo Click-to-Draw**: Dibuja polígonos haciendo click directamente en el mapa 3D
- **Auto-Cierre Inteligente**: El polígono se cierra automáticamente al conectar con el punto inicial
- **Gestión Completa**: CRUD de lotes con metadata (estado, precio, área, número)

### 🎯 Visualización Frontend
- **Polígonos Dinámicos**: Colores automáticos según estado (Verde=Disponible, Amarillo=Reservado, Rojo=Vendido)
- **Efecto Pulse**: Animación de pulso en el centroide de cada lote con el número
- **100% Responsive**: Optimizado para mobile y desktop con controles touch

### 📧 Sistema de Generación de Leads
- **Off-Canvas Sidebar**: Panel lateral moderno con detalles del lote
- **WhatsApp**: Enlace dinámico pre-configurado con mensaje personalizado
- **Email Template de Lujo**: Plantilla HTML profesional con diseño corporativo
- **Formulario de Contacto**: AJAX con validación completa

## 📋 Requisitos

- WordPress 5.8 o superior
- PHP 8.0 o superior
- API Key de Maptiler (gratuita): [Obtener aquí](https://www.maptiler.com/)

## 🚀 Instalación

1. **Descargar** el plugin:
   ```bash
   cd wp-content/plugins/
   # Copiar la carpeta masterplan-3d-pro-v2
   ```

2. **Activar** el plugin desde WordPress:
   - Ir a `Plugins` → `Plugins Instalados`
   - Buscar "MasterPlan 3D Pro"
   - Click en "Activar"

3. **Configurar** en `MasterPlan 3D` → `Configuración`:
   - Ingresar API Key de Maptiler
   - Configurar coordenadas del centro del mapa
   - Agregar número de WhatsApp
   - Configurar email de contacto

## 📖 Uso

### Crear un Lote

1. Ir a `Lotes` → `Agregar Nuevo`
2. Completar:
   - Título del lote
   - Número de lote
   - Estado (Disponible/Reservado/Vendido)
   - Precio y Área
   - Descripción
   - Imagen destacada
3. Guardar

### Dibujar Polígono en el Mapa

1. Ir a `MasterPlan 3D` → `Editor de Mapas`
2. Seleccionar el lote del dropdown
3. Click en "Dibujar Polígono"
4. Hacer click en el mapa para agregar puntos
5. El polígono se cierra automáticamente
6. Click en "Guardar"

### Mostrar el Mapa en el Frontend

Agregar el shortcode en cualquier página:

```
[masterplan_map]
```

Opcional con altura personalizada:

```
[masterplan_map height="800px"]
```

## 🎨 Estructura del Plugin

```
masterplan-3d-pro-v2/
├── admin/
│   ├── class-masterplan-admin.php
│   ├── class-masterplan-settings.php
│   ├── class-masterplan-lot-editor.php
│   ├── css/
│   │   └── admin-style.css
│   ├── js/
│   │   └── admin-builder.js
│   └── views/
│       ├── settings-page.php
│       └── lot-editor-page.php
├── includes/
│   ├── class-masterplan-core.php
│   ├── class-masterplan-loader.php
│   ├── class-masterplan-activator.php
│   ├── class-masterplan-deactivator.php
│   ├── class-masterplan-cpt.php
│   ├── class-masterplan-email.php
│   └── class-masterplan-api.php
├── public/
│   ├── class-masterplan-public.php
│   ├── css/
│   │   └── viewer.css
│   └── js/
│       └── frontend-viewer.js
├── masterplan-3d-pro.php
└── uninstall.php
```

## 🔒 Seguridad

- ✅ **Nonces** en todas las peticiones AJAX
- ✅ **Sanitización** de inputs (`sanitize_text_field`, `sanitize_email`)
- ✅ **Validación** de permisos con `current_user_can()`
- ✅ **Validación** de JSON en coordenadas de polígonos
- ✅ **Escape** de output con `esc_html`, `esc_attr`, `esc_url`

## 🌐 APIs y Tecnologías

- **MapLibre GL JS**: Motor de mapas 3D open-source
- **Maptiler**: Proveedor de tiles y terreno 3D
- **WordPress REST API**: Endpoints personalizados
- **WordPress Custom Post Types**: Gestión de lotes
- **WordPress Settings API**: Panel de configuración

## 📧 Endpoints REST

### GET `/wp-json/masterplan/v1/lots`
Obtiene todos los lotes publicados con sus polígonos.

**Respuesta:**
```json
[
  {
    "id": 123,
    "title": "Lote Premium Vista al Mar",
    "lot_number": "L-001",
    "status": "disponible",
    "price": 1500000,
    "area": 500,
    "coordinates": [[lng, lat], ...],
    "thumbnail": "https://..."
  }
]
```

### POST `/wp-json/masterplan/v1/contact`
Envía formulario de contacto.

**Parámetros:**
- `lot_id`: ID del lote
- `name`: Nombre del cliente
- `email`: Email del cliente
- `phone`: Teléfono
- `message`: Mensaje opcional
- `nonce`: Nonce de seguridad

## 🎨 Personalización

### Colores de Estado

Editar en `public/js/frontend-viewer.js`:

```javascript
'fill-color': [
    'match',
    ['get', 'status'],
    'disponible', '#10b981', // Verde
    'reservado', '#f59e0b',  // Amarillo
    'vendido', '#ef4444',    // Rojo
    '#64748b'
]
```

### Template de Email

Modificar `includes/class-masterplan-email.php` en el método `get_email_template()`.

## 📱 Soporte

- **Documentación**: Este README
- **Código**: Completamente comentado en español
- **Errores**: Revisar la consola del navegador y logs de WordPress

## 📝 Licencia

GPL-2.0+

## 👨‍💻 Créditos

Desarrollado con ❤️ usando:
- WordPress
- MapLibre GL JS
- Maptiler
- PHP 8+
- JavaScript ES6+

---

© 2026 MasterPlan 3D Pro. Todos los derechos reservados.
