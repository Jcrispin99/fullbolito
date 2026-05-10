<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Site;
use App\Models\SiteFormSubmission;
use App\Models\SiteNav;
use App\Models\SiteNavItem;
use App\Models\SitePage;
use App\Models\SitePageVersion;
use App\Models\SiteRedirect;
use App\Models\SiteSection;
use App\Models\SiteTheme;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class SiteSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ─── Site ─────────────────────────────────────────────────
        $site = Site::query()->firstOrCreate(
            [],
            [
                'name' => config('app.name', 'Mi Negocio'),
                'status' => 'published',
                'settings' => [
                    'google_analytics' => null,
                    'custom_css' => null,
                    'custom_head' => null,
                ],
            ],
        );

        // ─── Theme ────────────────────────────────────────────────
        SiteTheme::query()->firstOrCreate(
            ['site_id' => $site->id],
            [
                'name' => 'Tema Principal',
                'is_active' => true,
                'colors' => [
                    'primary' => '#2563eb',
                    'secondary' => '#0d9488',
                    'accent' => '#f59e0b',
                    'background' => '#ffffff',
                    'surface' => '#f9fafb',
                    'text' => '#111827',
                    'text_muted' => '#6b7280',
                ],
                'typography' => [
                    'heading_font' => 'system',
                    'body_font' => 'system',
                    'base_size' => 16,
                    'scale' => 1.25,
                    'line_height' => 1.6,
                ],
                'spacing' => [
                    'section_padding' => 80,
                    'container_max_width' => 1200,
                    'block_gap' => 0,
                ],
                'borders' => [
                    'radius' => 'md',
                    'button_radius' => 'md',
                ],
            ],
        );

        // ─── Navigation ───────────────────────────────────────────
        $headerNav = SiteNav::query()->firstOrCreate(
            ['site_id' => $site->id, 'location' => 'header'],
            [
                'config' => [
                    'bg_color' => '#111827',
                    'text_color' => '#ffffff',
                    'font' => 'system',
                    'sticky' => true,
                    'logo' => [
                        'type' => 'text',
                        'text' => config('app.name', 'Mi Negocio'),
                        'image_url' => null,
                        'href' => '/',
                        'position' => 'left',
                    ],
                    'menu' => [
                        'position' => 'right',
                        'style' => 'default',
                        'include_home' => false,
                    ],
                ],
            ],
        );

        SiteNav::query()->firstOrCreate(
            ['site_id' => $site->id, 'location' => 'footer'],
            [
                'config' => [
                    'bg_color' => '#111827',
                    'text_color' => '#9ca3af',
                    'font' => 'system',
                    'sticky' => false,
                    'logo' => [
                        'type' => 'text',
                        'text' => config('app.name', 'Mi Negocio'),
                        'image_url' => null,
                        'href' => '/',
                        'position' => 'left',
                    ],
                    'menu' => [
                        'position' => 'right',
                        'style' => 'default',
                        'include_home' => false,
                    ],
                ],
            ],
        );

        // Nav items
        if ($headerNav->rootItems()->count() === 0) {
            SiteNavItem::create(['nav_id' => $headerNav->id, 'label' => 'Inicio', 'type' => 'page', 'target' => '/', 'sort_order' => 0]);
            SiteNavItem::create(['nav_id' => $headerNav->id, 'label' => 'Nosotros', 'type' => 'page', 'target' => 'nosotros', 'sort_order' => 1]);
            SiteNavItem::create(['nav_id' => $headerNav->id, 'label' => 'Servicios', 'type' => 'page', 'target' => 'servicios', 'sort_order' => 2]);
            SiteNavItem::create(['nav_id' => $headerNav->id, 'label' => 'Contacto', 'type' => 'page', 'target' => 'contacto', 'sort_order' => 3]);
        }

        // ─── Pages + Sections ─────────────────────────────────────

        // --- HOME ---
        $home = SitePage::query()->firstOrCreate(
            ['site_id' => $site->id, 'slug' => 'home'],
            [
                'title' => 'Inicio',
                'type' => 'static',
                'status' => 'published',
                'is_homepage' => true,
                'seo_title' => config('app.name') . ' - Bienvenidos',
                'seo_description' => 'El mejor lugar para ti. Descubre nuestros productos y servicios.',
                'published_at' => now(),
            ],
        );

        if ($home->sections()->count() === 0) {
            SiteSection::create([
                'page_id' => $home->id,
                'block_type_key' => 'hero_banner',
                'sort_order' => 0,
                'layout' => ['width' => 'full', 'padding_y' => 'none', 'bg_type' => 'color', 'bg_value' => '#1e3a5f', 'visibility' => 'all'],
                'content' => [
                    'heading' => 'Bienvenidos a ' . config('app.name'),
                    'subheading' => 'Tu plataforma digital para crecer',
                    'cta_text' => 'Conocer mas',
                    'cta_url' => '/nosotros',
                    'cta_style' => 'solid',
                    'overlay_opacity' => 40,
                    'height' => 'lg',
                    'text_align' => 'center',
                ],
                'style_overrides' => ['heading_color' => '#ffffff', 'cta_bg' => '#2563eb', 'cta_color' => '#ffffff'],
                'is_visible' => true,
            ]);

            SiteSection::create([
                'page_id' => $home->id,
                'block_type_key' => 'features_grid',
                'sort_order' => 1,
                'layout' => ['width' => 'contained', 'padding_y' => 'lg', 'bg_type' => 'none', 'bg_value' => null, 'visibility' => 'all'],
                'content' => [
                    'heading' => 'Por que elegirnos',
                    'subheading' => 'Lo que nos hace diferentes',
                    'items' => [
                        ['icon' => 'star', 'title' => 'Facil de usar', 'description' => 'Interfaz intuitiva que cualquier persona puede manejar sin capacitacion.'],
                        ['icon' => 'zap', 'title' => 'Rapido y confiable', 'description' => 'Rendimiento optimizado para que tu negocio nunca se detenga.'],
                        ['icon' => 'shield', 'title' => 'Seguro', 'description' => 'Tus datos protegidos con los mas altos estandares de seguridad.'],
                    ],
                    'columns' => '3',
                ],
                'is_visible' => true,
            ]);

            SiteSection::create([
                'page_id' => $home->id,
                'block_type_key' => 'stats_block',
                'sort_order' => 2,
                'layout' => ['width' => 'contained', 'padding_y' => 'lg', 'bg_type' => 'color', 'bg_value' => '#f0f9ff', 'visibility' => 'all'],
                'content' => [
                    'heading' => 'Numeros que hablan',
                    'items' => [
                        ['value' => '500+', 'label' => 'Clientes activos'],
                        ['value' => '99.9%', 'label' => 'Uptime garantizado'],
                        ['value' => '24/7', 'label' => 'Soporte disponible'],
                        ['value' => '4.9', 'label' => 'Calificacion promedio'],
                    ],
                    'columns' => '4',
                ],
                'style_overrides' => ['value_color' => '#2563eb'],
                'is_visible' => true,
            ]);

            SiteSection::create([
                'page_id' => $home->id,
                'block_type_key' => 'cta_banner',
                'sort_order' => 3,
                'layout' => ['width' => 'full', 'padding_y' => 'lg', 'bg_type' => 'color', 'bg_value' => '#2563eb', 'visibility' => 'all'],
                'content' => [
                    'heading' => 'Listo para empezar?',
                    'body' => 'Crea tu cuenta hoy y lleva tu negocio al siguiente nivel.',
                    'cta_text' => 'Contactanos',
                    'cta_url' => '/contacto',
                    'cta_style' => 'solid',
                ],
                'style_overrides' => ['heading_color' => '#ffffff', 'body_color' => '#dbeafe', 'cta_bg' => '#ffffff', 'cta_color' => '#2563eb'],
                'is_visible' => true,
            ]);

            // Create a version snapshot for the homepage
            SitePageVersion::create([
                'page_id' => $home->id,
                'version_number' => 1,
                'title' => $home->title,
                'slug' => $home->slug,
                'sections_snapshot' => $home->sections->map(fn ($s) => [
                    'block_type_key' => $s->block_type_key,
                    'sort_order' => $s->sort_order,
                    'layout' => $s->layout,
                    'content' => $s->content,
                    'style_overrides' => $s->style_overrides,
                    'is_visible' => $s->is_visible,
                ])->toArray(),
                'seo_snapshot' => [
                    'seo_title' => $home->seo_title,
                    'seo_description' => $home->seo_description,
                    'og_image_asset_id' => null,
                ],
                'reason' => 'publish',
            ]);
        }

        // --- NOSOTROS ---
        $about = SitePage::query()->firstOrCreate(
            ['site_id' => $site->id, 'slug' => 'nosotros'],
            [
                'title' => 'Nosotros',
                'type' => 'static',
                'status' => 'published',
                'is_homepage' => false,
                'seo_title' => 'Sobre Nosotros - ' . config('app.name'),
                'seo_description' => 'Conoce nuestra historia y lo que nos impulsa cada dia.',
                'published_at' => now(),
            ],
        );

        if ($about->sections()->count() === 0) {
            SiteSection::create([
                'page_id' => $about->id,
                'block_type_key' => 'heading_block',
                'sort_order' => 0,
                'layout' => ['width' => 'contained', 'padding_y' => 'lg', 'bg_type' => 'none', 'bg_value' => null, 'visibility' => 'all'],
                'content' => ['text' => 'Nuestra Historia', 'subtitle' => 'Desde 2018 creando soluciones digitales', 'level' => 'h1', 'text_align' => 'center'],
                'is_visible' => true,
            ]);

            SiteSection::create([
                'page_id' => $about->id,
                'block_type_key' => 'text_block',
                'sort_order' => 1,
                'layout' => ['width' => 'narrow', 'padding_y' => 'md', 'bg_type' => 'none', 'bg_value' => null, 'visibility' => 'all'],
                'content' => [
                    'heading' => '',
                    'body' => "Comenzamos como un pequeno equipo con una gran vision: hacer la tecnologia accesible para todos los negocios. Hoy ayudamos a cientos de empresas a digitalizar sus operaciones.\n\nNuestro compromiso es simple: crear herramientas potentes pero faciles de usar, con soporte humano y cercano. Cada cliente es parte de nuestra historia.",
                    'text_align' => 'left',
                ],
                'is_visible' => true,
            ]);

            SiteSection::create([
                'page_id' => $about->id,
                'block_type_key' => 'text_image',
                'sort_order' => 2,
                'layout' => ['width' => 'contained', 'padding_y' => 'lg', 'bg_type' => 'color', 'bg_value' => '#f9fafb', 'visibility' => 'all'],
                'content' => [
                    'heading' => 'Nuestro Equipo',
                    'body' => 'Un equipo multidisciplinario de profesionales apasionados por la tecnologia y el servicio al cliente. Ingenieros, disenadores y consultores trabajando juntos.',
                    'cta_text' => 'Trabaja con nosotros',
                    'cta_url' => '/contacto',
                    'image_fit' => 'cover',
                ],
                'is_visible' => true,
            ]);
        }

        // --- SERVICIOS ---
        $servicios = SitePage::query()->firstOrCreate(
            ['site_id' => $site->id, 'slug' => 'servicios'],
            [
                'title' => 'Servicios',
                'type' => 'static',
                'status' => 'published',
                'is_homepage' => false,
                'seo_title' => 'Servicios - ' . config('app.name'),
                'seo_description' => 'Conoce todos nuestros servicios y soluciones para tu negocio.',
                'published_at' => now(),
            ],
        );

        if ($servicios->sections()->count() === 0) {
            SiteSection::create([
                'page_id' => $servicios->id,
                'block_type_key' => 'heading_block',
                'sort_order' => 0,
                'layout' => ['width' => 'contained', 'padding_top' => 'xl', 'padding_bottom' => 'md', 'padding_x' => 'md', 'margin_top' => 'none', 'margin_bottom' => 'none', 'bg_type' => 'none', 'bg_value' => null, 'bg_overlay' => null, 'bg_position' => 'center', 'bg_size' => 'cover', 'text_align' => 'center', 'anchor_id' => null, 'visibility' => 'all'],
                'content' => ['text' => 'Nuestros Servicios', 'subtitle' => 'Soluciones adaptadas a tu negocio', 'level' => 'h1', 'text_align' => 'center'],
                'is_visible' => true,
            ]);

            SiteSection::create([
                'page_id' => $servicios->id,
                'block_type_key' => 'features_grid',
                'sort_order' => 1,
                'layout' => ['width' => 'contained', 'padding_top' => 'md', 'padding_bottom' => 'lg', 'padding_x' => 'md', 'margin_top' => 'none', 'margin_bottom' => 'none', 'bg_type' => 'none', 'bg_value' => null, 'bg_overlay' => null, 'bg_position' => 'center', 'bg_size' => 'cover', 'text_align' => 'left', 'anchor_id' => null, 'visibility' => 'all'],
                'content' => [
                    'heading' => '',
                    'subheading' => '',
                    'items' => [
                        ['icon' => 'globe', 'title' => 'Sitio Web', 'description' => 'Crea tu presencia online con nuestro constructor de sitios web profesional.'],
                        ['icon' => 'shopping-cart', 'title' => 'E-Commerce', 'description' => 'Vende tus productos online con catalogo, carrito y pasarela de pagos.'],
                        ['icon' => 'bar-chart', 'title' => 'Reportes', 'description' => 'Visualiza el rendimiento de tu negocio con dashboards en tiempo real.'],
                        ['icon' => 'users', 'title' => 'CRM', 'description' => 'Gestiona tus clientes, contactos y oportunidades de venta.'],
                        ['icon' => 'calendar', 'title' => 'Reservas', 'description' => 'Sistema de reservas online para hoteles, restaurantes y servicios.'],
                        ['icon' => 'package', 'title' => 'Inventario', 'description' => 'Control completo de stock, almacenes y movimientos de productos.'],
                    ],
                    'columns' => '3',
                ],
                'is_visible' => true,
            ]);

            SiteSection::create([
                'page_id' => $servicios->id,
                'block_type_key' => 'cta_banner',
                'sort_order' => 2,
                'layout' => ['width' => 'full', 'padding_top' => 'xl', 'padding_bottom' => 'xl', 'padding_x' => 'md', 'margin_top' => 'none', 'margin_bottom' => 'none', 'bg_type' => 'color', 'bg_value' => '#0d9488', 'bg_overlay' => null, 'bg_position' => 'center', 'bg_size' => 'cover', 'text_align' => 'center', 'anchor_id' => null, 'visibility' => 'all'],
                'content' => [
                    'heading' => 'Necesitas algo personalizado?',
                    'body' => 'Contactanos y te ayudamos a encontrar la solucion perfecta para tu negocio.',
                    'cta_text' => 'Hablar con ventas',
                    'cta_url' => '/contacto',
                    'cta_style' => 'solid',
                ],
                'style_overrides' => ['heading_color' => '#ffffff', 'body_color' => '#ccfbf1', 'cta_bg' => '#ffffff', 'cta_color' => '#0d9488'],
                'is_visible' => true,
            ]);
        }

        // --- CONTACTO ---
        $contact = SitePage::query()->firstOrCreate(
            ['site_id' => $site->id, 'slug' => 'contacto'],
            [
                'title' => 'Contacto',
                'type' => 'static',
                'status' => 'published',
                'is_homepage' => false,
                'seo_title' => 'Contacto - ' . config('app.name'),
                'seo_description' => 'Contactanos para consultas, demos o soporte.',
                'published_at' => now(),
            ],
        );

        if ($contact->sections()->count() === 0) {
            SiteSection::create([
                'page_id' => $contact->id,
                'block_type_key' => 'heading_block',
                'sort_order' => 0,
                'layout' => ['width' => 'contained', 'padding_y' => 'lg', 'bg_type' => 'none', 'bg_value' => null, 'visibility' => 'all'],
                'content' => ['text' => 'Contactanos', 'subtitle' => 'Estamos aqui para ayudarte', 'level' => 'h1', 'text_align' => 'center'],
                'is_visible' => true,
            ]);

            $contactSection = SiteSection::create([
                'page_id' => $contact->id,
                'block_type_key' => 'contact_form',
                'sort_order' => 1,
                'layout' => ['width' => 'narrow', 'padding_y' => 'md', 'bg_type' => 'none', 'bg_value' => null, 'visibility' => 'all'],
                'content' => [
                    'heading' => '',
                    'description' => 'Llena el formulario y te responderemos lo antes posible.',
                    'email_to' => 'info@example.com',
                    'fields' => [
                        ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                        ['name' => 'phone', 'label' => 'Telefono', 'type' => 'text', 'required' => false],
                        ['name' => 'message', 'label' => 'Mensaje', 'type' => 'textarea', 'required' => true],
                    ],
                    'submit_text' => 'Enviar mensaje',
                    'success_message' => 'Gracias! Te responderemos pronto.',
                ],
                'is_visible' => true,
            ]);

            SiteSection::create([
                'page_id' => $contact->id,
                'block_type_key' => 'faq_accordion',
                'sort_order' => 2,
                'layout' => ['width' => 'narrow', 'padding_y' => 'lg', 'bg_type' => 'none', 'bg_value' => null, 'visibility' => 'all'],
                'content' => [
                    'heading' => 'Preguntas frecuentes',
                    'items' => [
                        ['question' => 'Tienen periodo de prueba?', 'answer' => 'Si, ofrecemos 14 dias de prueba gratuita sin compromisos ni tarjeta de credito.'],
                        ['question' => 'Puedo migrar mis datos?', 'answer' => 'Si, nuestro equipo te ayuda con la migracion de datos desde tu sistema actual sin costo adicional.'],
                        ['question' => 'Que metodos de pago aceptan?', 'answer' => 'Aceptamos tarjetas de credito/debito, transferencias bancarias y PayPal.'],
                        ['question' => 'Ofrecen soporte tecnico?', 'answer' => 'Si, soporte 24/7 por chat, email y telefono en todos los planes.'],
                    ],
                ],
                'is_visible' => true,
            ]);
        }

        // ─── Global Section ───────────────────────────────────────
        SiteSection::query()->firstOrCreate(
            ['site_id' => $site->id, 'is_global' => true, 'global_name' => 'CTA Newsletter'],
            [
                'page_id' => null,
                'block_type_key' => 'cta_banner',
                'sort_order' => 0,
                'layout' => ['width' => 'full', 'padding_y' => 'lg', 'bg_type' => 'color', 'bg_value' => '#0d9488', 'visibility' => 'all'],
                'content' => [
                    'heading' => 'Suscribete a nuestro newsletter',
                    'body' => 'Recibe ofertas exclusivas y novedades directamente en tu bandeja de entrada.',
                    'cta_text' => 'Suscribirse',
                    'cta_url' => '#newsletter',
                    'cta_style' => 'solid',
                ],
                'style_overrides' => ['heading_color' => '#ffffff', 'body_color' => '#ccfbf1', 'cta_bg' => '#ffffff', 'cta_color' => '#0d9488'],
                'is_visible' => true,
            ],
        );

        // ─── Form Submissions (demo data) ─────────────────────────
        if (SiteFormSubmission::query()->count() === 0) {
            $contactPageId = $contact->id;
            $contactSectionId = $contactSection->id ?? null;

            SiteFormSubmission::create([
                'site_id' => $site->id,
                'page_id' => $contactPageId,
                'section_id' => $contactSectionId,
                'form_type' => 'contact',
                'data' => ['name' => 'Maria Garcia', 'email' => 'maria@example.com', 'phone' => '+51 999 111 222', 'message' => 'Hola, me interesa el plan empresarial. Podrian agendarme una demo esta semana?'],
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0',
                'status' => 'new',
            ]);

            SiteFormSubmission::create([
                'site_id' => $site->id,
                'page_id' => $contactPageId,
                'section_id' => $contactSectionId,
                'form_type' => 'contact',
                'data' => ['name' => 'Carlos Lopez', 'email' => 'carlos@example.com', 'phone' => '', 'message' => 'Buenas tardes, necesito informacion sobre integraciones con pasarelas de pago locales.'],
                'ip_address' => '192.168.1.101',
                'user_agent' => 'Mozilla/5.0',
                'status' => 'new',
            ]);

            SiteFormSubmission::create([
                'site_id' => $site->id,
                'page_id' => $contactPageId,
                'section_id' => $contactSectionId,
                'form_type' => 'contact',
                'data' => ['name' => 'Ana Torres', 'email' => 'ana.torres@example.com', 'phone' => '+51 988 333 444', 'message' => 'Excelente plataforma! La migracion fue muy fluida y el soporte respondio todas mis dudas rapidamente.'],
                'ip_address' => '192.168.1.102',
                'user_agent' => 'Mozilla/5.0',
                'status' => 'read',
                'read_at' => now()->subDay(),
            ]);

            SiteFormSubmission::create([
                'site_id' => $site->id,
                'page_id' => $contactPageId,
                'section_id' => $contactSectionId,
                'form_type' => 'contact',
                'data' => ['name' => 'Roberto Diaz', 'email' => 'roberto@example.com', 'phone' => '+51 977 555 666', 'message' => 'Necesito una cotizacion para 50 usuarios. Tenemos 3 sucursales y necesitamos multi-almacen.'],
                'ip_address' => '192.168.1.103',
                'user_agent' => 'Mozilla/5.0',
                'status' => 'read',
                'read_at' => now()->subDays(3),
            ]);

            SiteFormSubmission::create([
                'site_id' => $site->id,
                'page_id' => $contactPageId,
                'section_id' => $contactSectionId,
                'form_type' => 'contact',
                'data' => ['name' => 'Laura Mendez', 'email' => 'laura@example.com', 'phone' => '', 'message' => 'El reporte de ventas no esta mostrando los datos del mes pasado. Pueden revisar?'],
                'ip_address' => '192.168.1.104',
                'user_agent' => 'Mozilla/5.0',
                'status' => 'archived',
                'read_at' => now()->subWeek(),
            ]);
        }

        // ─── Redirect (demo) ─────────────────────────────────────
        SiteRedirect::query()->firstOrCreate(
            ['site_id' => $site->id, 'from_slug' => 'about'],
            ['to_slug' => 'nosotros', 'type' => 301, 'is_active' => true],
        );

        SiteRedirect::query()->firstOrCreate(
            ['site_id' => $site->id, 'from_slug' => 'services'],
            ['to_slug' => 'servicios', 'type' => 301, 'is_active' => true],
        );
    }
}
