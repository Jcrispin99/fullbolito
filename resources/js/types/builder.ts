// ─── Block Catalog ──────────────────────────────────────────────

export interface BlockFieldSchema {
  type: 'string' | 'rich_text' | 'number' | 'boolean' | 'enum' | 'color' | 'url' | 'asset' | 'asset_list'
  required?: boolean
  label: string
  default?: any
  options?: string[]
  min?: number
  max?: number
}

export interface BlockSchema {
  fields: Record<string, BlockFieldSchema>
  style_fields?: Record<string, BlockFieldSchema>
}

export interface BlockType {
  id: number
  key: string
  name: string
  description: string | null
  category: 'headers' | 'content' | 'media' | 'grids' | 'interactive' | 'commerce'
  icon: string | null
  feature_gate: string | null
  schema: BlockSchema
  default_content: Record<string, any>
  default_layout: SectionLayout
  sort_order: number
  is_active: boolean
}

// ─── Site ───────────────────────────────────────────────────────

export interface SiteSettings {
  google_analytics: string | null
  custom_css: string | null
  custom_head: string | null
}

export interface Site {
  id: number
  name: string
  status: 'draft' | 'published'
  custom_domain: string | null
  domain_status: 'pending' | 'verifying' | 'active' | 'failed' | null
  domain_verified_at: string | null
  settings: SiteSettings | null
  theme: SiteTheme | null
  created_at: string
  updated_at: string
}

// ─── Theme ──────────────────────────────────────────────────────

export interface ThemeColors {
  primary: string
  secondary: string
  accent: string
  background: string
  surface: string
  text: string
  text_muted: string
}

export interface ThemeTypography {
  heading_font: string
  body_font: string
  base_size: number
  scale: number
  line_height: number
}

export interface ThemeSpacing {
  section_padding: number
  container_max_width: number
  block_gap: number
}

export interface ThemeBorders {
  radius: 'none' | 'sm' | 'md' | 'lg' | 'full'
  button_radius: 'none' | 'sm' | 'md' | 'lg' | 'full'
}

export interface SiteTheme {
  id: number
  name: string
  is_active: boolean
  colors: ThemeColors
  typography: ThemeTypography
  spacing: ThemeSpacing
  borders: ThemeBorders
  created_at: string
  updated_at: string
}

// ─── Page ───────────────────────────────────────────────────────

export interface SitePage {
  id: number
  title: string
  slug: string
  type: 'static' | 'blog' | 'product_list' | 'product_detail'
  status: 'draft' | 'published' | 'archived'
  is_homepage: boolean
  seo_title: string | null
  seo_description: string | null
  og_image_asset_id: number | null
  published_at: string | null
  sections?: SiteSection[]
  created_at: string
  updated_at: string
}

// ─── Section ────────────────────────────────────────────────────

export type SpacingSize = 'none' | 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'

export interface SectionLayout {
  width: 'full' | 'contained' | 'narrow'
  padding_top: SpacingSize
  padding_bottom: SpacingSize
  padding_x: 'none' | 'sm' | 'md' | 'lg'
  margin_top: SpacingSize
  margin_bottom: SpacingSize
  bg_type: 'none' | 'color' | 'image' | 'gradient' | 'video'
  bg_value: string | null
  bg_overlay: string | null
  bg_position: 'center' | 'top' | 'bottom' | 'left' | 'right'
  bg_size: 'cover' | 'contain'
  text_align: 'left' | 'center' | 'right'
  anchor_id: string | null
  visibility: 'all' | 'desktop' | 'tablet' | 'mobile'
  // Legacy compat
  padding_y?: 'none' | 'sm' | 'md' | 'lg' | 'xl'
}

export interface SiteSection {
  id: number
  parent_id: number | null
  column_index: number
  block_type_key: string
  sort_order: number
  position_x: number
  position_y: number
  element_width: number | null
  element_height: number | null
  rotation: number
  position_mode: 'flow' | 'absolute'
  layout: SectionLayout
  content: Record<string, any>
  style_overrides: Record<string, any> | null
  is_visible: boolean
  is_global: boolean
  global_name: string | null
  children?: SiteSection[]
  created_at: string
  updated_at: string
}

// ─── Asset ──────────────────────────────────────────────────────

export interface AssetVariant {
  url: string
  width: number
  height: number
}

export interface SiteAsset {
  id: number
  original_name: string
  url: string
  mime_type: string
  size_bytes: number
  width: number | null
  height: number | null
  dominant_color: string | null
  alt_text: string | null
  variants: Record<string, AssetVariant> | null
  folder: string
  created_at: string
}

// ─── Navigation ─────────────────────────────────────────────────

export interface NavLogo {
  type: 'text' | 'image'
  text: string | null
  image_url: string | null
  href: string
  position: 'left' | 'center' | 'right'
}

export interface NavMenu {
  position: 'left' | 'center' | 'right'
  style: 'default' | 'split'
  include_home: boolean
}

export interface NavConfig {
  bg_color: string
  text_color: string
  font: string
  sticky: boolean
  logo: NavLogo
  menu: NavMenu
}

export interface SiteNavItem {
  id: number
  label: string
  type: 'page' | 'url' | 'anchor'
  target: string
  sort_order: number
  open_new_tab: boolean
  children?: SiteNavItem[]
}

export interface SiteNav {
  id: number
  location: 'header' | 'footer'
  config: NavConfig
  items: SiteNavItem[]
  created_at: string
  updated_at: string
}

// ─── Form Submissions ──────────────────────────────────────────

export interface FormSubmission {
  id: number
  site_id: number
  page_id: number | null
  section_id: number | null
  form_type: string
  data: Record<string, any>
  ip_address: string | null
  status: 'new' | 'read' | 'archived'
  read_at: string | null
  page?: SitePage
  created_at: string
  updated_at: string
}

export interface FormSubmissionStats {
  total: number
  new: number
  read: number
  archived: number
}

// ─── Page Versions ─────────────────────────────────────────────

export interface SitePageVersion {
  id: number
  page_id: number
  version_number: number
  title: string
  slug: string
  sections_snapshot: Record<string, any>[]
  seo_snapshot: Record<string, any> | null
  reason: string
  created_by: number | null
  created_at: string
}

// ─── Redirects ─────────────────────────────────────────────────

export interface SiteRedirect {
  id: number
  site_id: number
  from_slug: string
  to_slug: string
  type: 301 | 302
  is_active: boolean
  created_at: string
  updated_at: string
}

// ─── Templates ─────────────────────────────────────────────────

export interface SiteTemplate {
  id: number
  name: string
  slug: string
  description: string | null
  thumbnail_url: string | null
  category: string
  is_active: boolean
  sort_order: number
  created_at: string
}

// ─── Public Site (combined response) ────────────────────────────

export interface PublicSiteData {
  name: string
  status: string
  theme: SiteTheme | null
  navs: SiteNav[]
}
