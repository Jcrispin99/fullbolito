# Website Builder — Documentacion Tecnica

> SaaS multi-tenant website builder integrado. Los tenants pueden construir su sitio web publico desde un editor visual inline (sin salir del sitio).

---

## Arquitectura General

```
Central DB                    Tenant DB
┌─────────────────┐           ┌──────────────────────────┐
│ site_block_catalog│          │ sites                    │
│ site_templates   │           │ site_pages               │
└─────────────────┘           │ site_sections             │
                              │ site_themes               │
                              │ site_navs / site_nav_items│
                              │ site_assets               │
                              │ site_form_submissions     │
                              │ site_page_versions        │
                              │ site_redirects            │
                              └──────────────────────────┘
```

- **Catalogo de bloques** (`site_block_catalog`) vive en la DB central, compartido por todos los tenants
- **Todo lo demas** vive en la DB del tenant

---

## Backend

### Modelos

| Modelo | Tabla | Descripcion |
|--------|-------|-------------|
| `Site` | `sites` | Sitio principal del tenant. Campos: name, status (draft/published), custom_domain, domain_status, settings (JSON: google_analytics, custom_css, custom_head) |
| `SitePage` | `site_pages` | Paginas del sitio. Campos: title, slug, type (static/blog/product_list/product_detail), status (draft/published/archived), is_homepage, seo_title, seo_description, published_at |
| `SiteSection` | `site_sections` | Bloques/secciones de una pagina. Campos: block_type_key, sort_order, layout (JSON), content (JSON), style_overrides (JSON), is_visible, position_mode (flow/absolute), position_x/y, element_width/height, rotation, parent_id, column_index, is_global, global_name |
| `SiteTheme` | `site_themes` | Tema visual. Campos: colors (JSON), typography (JSON), spacing (JSON), borders (JSON), is_active |
| `SiteNav` | `site_navs` | Navegacion header/footer. Campos: location (header/footer), config (JSON: bg_color, text_color, logo, menu) |
| `SiteNavItem` | `site_nav_items` | Items del menu. Self-referencial (parent_id). Campos: label, type (page/url/anchor), target, sort_order, open_new_tab |
| `SiteAsset` | `site_assets` | Media/archivos. Campos: original_name, path, mime_type, size_bytes, width, height, dominant_color, alt_text, variants (JSON), folder |
| `SiteBlockCatalog` | `site_block_catalog` | (Central) Definiciones de tipos de bloque. Campos: key, name, category, schema (JSON: fields + style_fields), default_content, default_layout, feature_gate |
| `SiteFormSubmission` | `site_form_submissions` | Envios de formularios. Campos: form_type, data (JSON), ip_address, status (new/read/archived), read_at |
| `SitePageVersion` | `site_page_versions` | Snapshots de paginas al publicar. Campos: version_number, sections_snapshot (JSON), seo_snapshot (JSON), reason |
| `SiteRedirect` | `site_redirects` | Redirecciones 301/302. Campos: from_slug, to_slug, type, is_active. Se crean automaticamente al cambiar slug de pagina |
| `SiteTemplate` | `site_templates` | (Central) Templates de sitio. Campos: name, slug, site_snapshot (JSON completo del sitio) |

### Controllers (en `App\Http\Controllers\Api\Tenant\V1`)

| Controller | Prefijo ruta | Funcion |
|------------|-------------|---------|
| `BuilderSiteController` | `builder/site` | GET/PUT site, PUT theme, PUT/POST/DELETE domain |
| `BuilderPageController` | `builder/pages` | CRUD paginas + publish/unpublish/duplicate/preview + versions/revert |
| `BuilderSectionController` | `builder/pages/{page}/sections` | CRUD secciones + reorder/duplicate |
| `BuilderNavController` | `builder/navs` | CRUD navegacion + items + reorder |
| `BuilderMediaController` | `builder/media` | Upload/CRUD assets. Despacha `ProcessSiteAsset` job para thumbnails |
| `BuilderBlockTypeController` | `builder/block-types` | Listar catalogo de bloques |
| `BuilderFormSubmissionController` | `builder/form-submissions` | Listar/ver/archivar/eliminar/exportar submissions + stats |
| `BuilderRedirectController` | `builder/redirects` | CRUD redirects |
| `BuilderGlobalSectionController` | `builder/global-sections` | CRUD secciones globales + add-to-page |
| `BuilderTemplateController` | `builder/templates` | Listar/aplicar templates + save-as-template + duplicate-site |
| `PublicSiteController` | `public/` | Endpoints publicos sin auth: site, pages, page/{slug}, forms/submit |

### Servicios

- **`BlockSchemaValidator`**: Valida content y style_overrides contra el schema del catalogo de bloques
- **`ProcessSiteAsset`** (Job): Genera thumbnails (sm 320px, md 768px, lg 1280px) en WebP, extrae color dominante

### Rutas

```
# Autenticadas (auth:sanctum)
GET/PUT    /api/v1/builder/site
PUT        /api/v1/builder/site/theme
PUT/POST/DELETE /api/v1/builder/site/domain[/verify]
GET/POST   /api/v1/builder/pages
GET/PUT/DELETE /api/v1/builder/pages/{page}
GET        /api/v1/builder/pages/{page}/preview
GET        /api/v1/builder/pages/{page}/versions
POST       /api/v1/builder/pages/{page}/versions/{version}/revert
POST       /api/v1/builder/pages/{page}/publish|unpublish|duplicate
GET/POST   /api/v1/builder/pages/{page}/sections
PUT/DELETE /api/v1/builder/pages/{page}/sections/{section}
PUT        /api/v1/builder/pages/{page}/sections/reorder
POST       /api/v1/builder/pages/{page}/sections/{section}/duplicate
GET/POST   /api/v1/builder/media
PUT/DELETE /api/v1/builder/media/{asset}
GET        /api/v1/builder/block-types[/{key}]
GET/PUT    /api/v1/builder/navs[/{location}]
POST/PUT/DELETE /api/v1/builder/navs/{location}/items[/{item}]
GET        /api/v1/builder/form-submissions[/stats|/export]
PATCH      /api/v1/builder/form-submissions/{id}/read|archive
GET/POST   /api/v1/builder/redirects
GET/POST   /api/v1/builder/global-sections
POST       /api/v1/builder/global-sections/{section}/add-to-page
GET/POST   /api/v1/builder/templates[/save]
POST       /api/v1/builder/templates/{template}/apply
POST       /api/v1/builder/site/duplicate

# Publicas (sin auth)
GET        /api/v1/public/site
GET        /api/v1/public/pages[/{slug}]
POST       /api/v1/public/forms/submit
```

---

## Frontend

### Layout del Editor (modo edicion)

```
┌──────────────────────────────────────────────────────────┐
│                    TOOLBAR (AdminToolbar.vue)             │
│  Admin | Paginas | Tema Nav Media Mail Config History     │
│                    Editando: Inicio ●  │ Descartar Guardar│
├──────────┬───────────────────────────────┬───────────────┤
│ LEFT     │       CANVAS (centro)         │    RIGHT      │
│ SIDEBAR  │                               │   SIDEBAR     │
│          │  Sitio renderizado en tarjeta  │               │
│ Tabs:    │  blanca sobre fondo gris.     │  Tabs:        │
│ Bloques  │                               │  Contenido    │
│ Capas    │  Click en bloque → selecciona │  Estilo       │
│ Paginas  │  FloatingToolbar aparece      │  Layout       │
│          │  encima del bloque            │  (+ posicion  │
│          │                               │   y rotacion) │
└──────────┴───────────────────────────────┴───────────────┘
```

### Componentes clave

```
resources/js/tenant/
├── components/
│   ├── AdminToolbar.vue          # Toolbar superior con iconos de paneles
│   ├── BuilderDrawer.vue         # Drawer generico (base para panels)
│   ├── MediaPickerModal.vue      # Modal para seleccionar/subir imagenes
│   ├── blocks/
│   │   ├── BlockRenderer.vue     # Dispatcher dinamico por block_type_key
│   │   ├── CanvasElement.vue     # Wrapper para elementos con drag/resize/rotate
│   │   ├── useBlockLayout.ts     # Composable para convertir layout JSON a CSS
│   │   ├── HeroBanner.vue        # Bloque: hero con titulo, subtitulo, CTA
│   │   ├── TextBlock.vue         # Bloque: texto rico
│   │   ├── TextImage.vue         # Bloque: texto + imagen (2 columnas)
│   │   ├── ImageText.vue         # Bloque: imagen + texto (2 columnas)
│   │   ├── HeadingBlock.vue      # Bloque: titulo H1-H6
│   │   ├── ImageBlock.vue        # Bloque: imagen individual
│   │   ├── ImageGallery.vue      # Bloque: galeria grid
│   │   ├── FeaturesGrid.vue      # Bloque: tarjetas de features
│   │   ├── StatsBlock.vue        # Bloque: numeros/estadisticas
│   │   ├── CtaBanner.vue         # Bloque: call to action
│   │   ├── FaqAccordion.vue      # Bloque: acordeon de FAQ
│   │   ├── ContactForm.vue       # Bloque: formulario funcional
│   │   ├── SpacerBlock.vue       # Bloque: espaciador
│   │   └── DividerBlock.vue      # Bloque: linea divisora
│   ├── editor/
│   │   ├── LeftSidebar.vue       # Sidebar izq: Bloques/Capas/Paginas
│   │   ├── RightSidebar.vue      # Sidebar der: Contenido/Estilo/Layout
│   │   └── FloatingToolbar.vue   # Toolbar flotante sobre bloque seleccionado
│   └── panels/
│       ├── ThemePanel.vue        # Drawer: colores, tipografia, bordes
│       ├── NavPanel.vue          # Drawer: items de navegacion
│       ├── MediaPanel.vue        # Drawer: galeria de media
│       ├── FormSubmissionsPanel.vue # Drawer: envios de formularios
│       ├── SettingsPanel.vue     # Drawer: general + dominio + redirects
│       └── VersionHistoryPanel.vue  # Drawer: historial de versiones
├── stores/
│   ├── builderPage.ts            # CRUD paginas
│   ├── builderSection.ts         # CRUD secciones con sistema de snapshot/save/discard
│   ├── siteConfig.ts             # Config del sitio y tema
│   ├── siteNav.ts                # Navegacion
│   ├── blockCatalog.ts           # Catalogo de bloques
│   ├── media.ts                  # Media/assets
│   ├── editorMode.ts             # Estado del editor (isEditing, activeDrawer, selectedSection)
│   ├── publicSite.ts             # Datos publicos del sitio
│   ├── formSubmission.ts         # Form submissions
│   ├── pageVersion.ts            # Versiones de pagina
│   ├── redirect.ts               # Redirects
│   ├── globalSection.ts          # Secciones globales
│   ├── template.ts               # Templates
│   └── customDomain.ts           # Dominio personalizado
└── views/
    ├── PublicWebsite.vue          # Homepage (editor 3-col o publico)
    └── PublicPage.vue             # Pagina por slug (editor 3-col o publico)
```

### Sistema de guardado

- **Edicion local**: todos los cambios de contenido/estilo/layout se acumulan en memoria
- **Indicador visual**: punto amarillo pulsante cuando hay cambios sin guardar
- **Boton "Guardar"**: envia todos los cambios al API de golpe
- **Boton "Descartar"**: revierte al estado original (snapshot tomado al entrar en edicion)
- **Operaciones estructurales** (agregar/eliminar/reordenar/duplicar bloques) van al API inmediatamente

### SectionLayout (estructura del JSON `layout`)

```typescript
{
  width: 'full' | 'contained' | 'narrow'
  padding_top: 'none' | 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  padding_bottom: SpacingSize
  padding_x: 'none' | 'sm' | 'md' | 'lg'
  margin_top: SpacingSize
  margin_bottom: SpacingSize
  bg_type: 'none' | 'color' | 'image' | 'gradient' | 'video'
  bg_value: string | null
  bg_overlay: string | null        // ej: '#00000066'
  bg_position: 'center' | 'top' | 'bottom' | 'left' | 'right'
  bg_size: 'cover' | 'contain'
  text_align: 'left' | 'center' | 'right'
  anchor_id: string | null         // para links internos #seccion
  visibility: 'all' | 'desktop' | 'tablet' | 'mobile'
}
```

### Campos de posicionamiento libre (en SiteSection)

```
position_mode: 'flow' | 'absolute'   # flow = apilado normal, absolute = posicion libre
position_x: float                     # % del contenedor
position_y: float                     # % del contenedor
element_width: float | null           # % del contenedor
element_height: float | null          # px
rotation: float                       # grados (-360 a 360)
parent_id: int | null                 # para nesting futuro
column_index: int                     # para columnas futuro
```

### Tipos de bloque actuales (SiteBlockCatalog)

| Key | Nombre | Categoria |
|-----|--------|-----------|
| `hero_banner` | Hero Banner | headers |
| `text_block` | Bloque de Texto | content |
| `text_image` | Texto + Imagen | content |
| `image_text` | Imagen + Texto | content |
| `heading_block` | Encabezado | content |
| `image_block` | Imagen | media |
| `image_gallery` | Galeria de Imagenes | media |
| `features_grid` | Grilla de Features | grids |
| `stats_block` | Estadisticas | grids |
| `cta_banner` | Call to Action | interactive |
| `faq_accordion` | Preguntas Frecuentes | interactive |
| `contact_form` | Formulario de Contacto | interactive |
| `reservation_widget` | Widget de Reservas | interactive |
| `spacer` | Espaciador | layout |
| `divider` | Divisor | layout |

### Schema de un bloque (ejemplo hero_banner)

```json
{
  "fields": {
    "heading": { "type": "rich_text", "required": true, "label": "Titulo" },
    "subheading": { "type": "rich_text", "required": false, "label": "Subtitulo" },
    "cta_text": { "type": "string", "required": false, "label": "Texto del boton" },
    "cta_url": { "type": "url", "required": false, "label": "URL del boton" },
    "bg_image": { "type": "asset", "required": false, "label": "Imagen de fondo" }
  },
  "style_fields": {
    "heading_color": { "type": "color", "label": "Color del titulo" },
    "cta_bg": { "type": "color", "label": "Color del boton fondo" }
  }
}
```

Tipos de campo soportados: `string`, `rich_text`, `number`, `boolean`, `enum`, `color`, `url`, `asset`, `asset_list`

---

## Storage de assets

- Archivos se guardan en `storage/{tenant_id}/app/public/sites/{site_id}/media/`
- Symlink: `public/storage/{tenant_id}` -> `storage/{tenant_id}/app/public/`
- URLs generadas: `{tenant_domain}/storage/{tenant_id}/sites/1/media/{uuid}.ext`
- Config en `config/tenancy.php`: `filesystem.suffix_base = ''` (carpeta = tenant id directo)
- Job `ProcessSiteAsset` genera variantes WebP (sm/md/lg) automaticamente

---

## Seeders

- `SiteBlockCatalogSeeder` (central): crea los 13 tipos de bloque en el catalogo
- `SiteSeeder` (tenant): crea sitio completo con 4 paginas (Inicio, Nosotros, Servicios, Contacto), nav items, secciones, form submissions de demo, redirects, 1 seccion global

---

## Pendiente (proximas fases)

### Fase 2: Nesting y columnas
- [ ] Usar `parent_id` y `column_index` para bloques hijos dentro de secciones
- [ ] Selector de presets de columnas (1-col, 2 iguales, 2/3+1/3, 3-col)
- [ ] Drag & drop real con libreria (vue-draggable-next)
- [ ] Eliminar bloques `text_image` / `image_text` — se logra con columnas

### Fase 3: Edicion avanzada
- [ ] Rich text con Tiptap (bold, italic, links, listas) en campos `rich_text`
- [ ] Edicion inline en canvas (click texto para editar directo)
- [ ] Preview responsive (toggle desktop/tablet/mobile en toolbar)
- [ ] Undo/Redo (stack de operaciones en editorMode store)
- [ ] Auto-save con debounce opcional

### Fase 4: Mejoras
- [ ] Tipo `repeater` en schema (reemplazar abuso de `asset_list` para FAQs, features, stats)
- [ ] Tipo `link` estructurado (url + label + target + style)
- [ ] Tipo `icon` con picker
- [ ] Tipo `page_link` para seleccionar paginas internas
- [ ] Secciones globales como referencia viva (no copia)
- [ ] Animaciones de entrada (fade-in, slide-up) en layout
- [ ] Custom CSS/JS por pagina (no solo por sitio)

### Bugs conocidos
- [ ] El symlink de storage debe crearse manualmente para cada tenant nuevo (`ln -sf storage/{id}/app/public public/storage/{id}`)
- [ ] Los diagnostics de PHPStan sobre JsonResource y generics de Eloquent son los estandar de Laravel — no son errores reales
