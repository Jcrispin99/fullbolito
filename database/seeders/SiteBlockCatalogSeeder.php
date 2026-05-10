<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SiteBlockCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class SiteBlockCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $blocks = [
            [
                'key' => 'hero_banner',
                'name' => 'Hero Banner',
                'description' => 'Banner principal con imagen de fondo, texto y botón de acción.',
                'category' => 'headers',
                'icon' => 'image',
                'feature_gate' => null,
                'sort_order' => 1,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'rich_text', 'required' => true, 'label' => 'Título'],
                        'subheading' => ['type' => 'rich_text', 'required' => false, 'label' => 'Subtítulo'],
                        'cta_text' => ['type' => 'string', 'required' => false, 'label' => 'Texto del botón', 'max' => 50],
                        'cta_url' => ['type' => 'url', 'required' => false, 'label' => 'URL del botón'],
                        'cta_style' => ['type' => 'enum', 'options' => ['solid', 'outline', 'ghost'], 'default' => 'solid', 'label' => 'Estilo del botón'],
                        'bg_image' => ['type' => 'asset', 'required' => false, 'label' => 'Imagen de fondo'],
                        'images' => ['type' => 'asset_list', 'required' => false, 'label' => 'Carrusel de imágenes', 'max' => 5],
                        'overlay_opacity' => ['type' => 'number', 'min' => 0, 'max' => 100, 'default' => 40, 'label' => 'Opacidad del overlay'],
                        'height' => ['type' => 'enum', 'options' => ['sm', 'md', 'lg', 'full'], 'default' => 'lg', 'label' => 'Altura'],
                        'text_align' => ['type' => 'enum', 'options' => ['left', 'center', 'right'], 'default' => 'center', 'label' => 'Alineación'],
                    ],
                    'style_fields' => [
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'heading_size' => ['type' => 'enum', 'options' => ['sm', 'md', 'lg', 'xl'], 'label' => 'Tamaño del título'],
                        'cta_color' => ['type' => 'color', 'label' => 'Color del botón texto'],
                        'cta_bg' => ['type' => 'color', 'label' => 'Color del botón fondo'],
                    ],
                ],
                'default_content' => [
                    'heading' => 'Bienvenidos',
                    'subheading' => 'Descubre lo que tenemos para ti',
                    'cta_text' => 'Comenzar',
                    'cta_url' => '#',
                    'cta_style' => 'solid',
                    'overlay_opacity' => 40,
                    'height' => 'lg',
                    'text_align' => 'center',
                ],
                'default_layout' => [
                    'width' => 'full',
                    'padding_y' => 'none',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'text_block',
                'name' => 'Bloque de Texto',
                'description' => 'Sección de texto rico con título y párrafo.',
                'category' => 'content',
                'icon' => 'type',
                'feature_gate' => null,
                'sort_order' => 2,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'rich_text', 'required' => false, 'label' => 'Título'],
                        'body' => ['type' => 'rich_text', 'required' => true, 'label' => 'Contenido'],
                        'text_align' => ['type' => 'enum', 'options' => ['left', 'center', 'right'], 'default' => 'left', 'label' => 'Alineación'],
                    ],
                    'style_fields' => [
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'body_color' => ['type' => 'color', 'label' => 'Color del texto'],
                    ],
                ],
                'default_content' => [
                    'heading' => '',
                    'body' => 'Escribe tu contenido aquí.',
                    'text_align' => 'left',
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'md',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'text_image',
                'name' => 'Texto + Imagen',
                'description' => 'Texto a la izquierda con imagen a la derecha.',
                'category' => 'content',
                'icon' => 'columns',
                'feature_gate' => null,
                'sort_order' => 3,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'rich_text', 'required' => false, 'label' => 'Título'],
                        'body' => ['type' => 'rich_text', 'required' => false, 'label' => 'Descripción'],
                        'cta_text' => ['type' => 'string', 'required' => false, 'label' => 'Texto del botón', 'max' => 50],
                        'cta_url' => ['type' => 'url', 'required' => false, 'label' => 'URL del botón'],
                        'image' => ['type' => 'asset', 'required' => false, 'label' => 'Imagen'],
                        'image_fit' => ['type' => 'enum', 'options' => ['cover', 'contain', 'fill'], 'default' => 'cover', 'label' => 'Ajuste de imagen'],
                    ],
                    'style_fields' => [
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'body_color' => ['type' => 'color', 'label' => 'Color del texto'],
                    ],
                ],
                'default_content' => [
                    'heading' => 'Sobre nosotros',
                    'body' => 'Cuéntale a tus visitantes quién eres.',
                    'cta_text' => '',
                    'cta_url' => '',
                    'image_fit' => 'cover',
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'lg',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'image_text',
                'name' => 'Imagen + Texto',
                'description' => 'Imagen a la izquierda con texto a la derecha.',
                'category' => 'content',
                'icon' => 'columns',
                'feature_gate' => null,
                'sort_order' => 4,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'rich_text', 'required' => false, 'label' => 'Título'],
                        'body' => ['type' => 'rich_text', 'required' => false, 'label' => 'Descripción'],
                        'cta_text' => ['type' => 'string', 'required' => false, 'label' => 'Texto del botón', 'max' => 50],
                        'cta_url' => ['type' => 'url', 'required' => false, 'label' => 'URL del botón'],
                        'image' => ['type' => 'asset', 'required' => false, 'label' => 'Imagen'],
                        'image_fit' => ['type' => 'enum', 'options' => ['cover', 'contain', 'fill'], 'default' => 'cover', 'label' => 'Ajuste de imagen'],
                    ],
                    'style_fields' => [
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'body_color' => ['type' => 'color', 'label' => 'Color del texto'],
                    ],
                ],
                'default_content' => [
                    'heading' => 'Nuestros servicios',
                    'body' => 'Describe los servicios que ofreces.',
                    'cta_text' => '',
                    'cta_url' => '',
                    'image_fit' => 'cover',
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'lg',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'heading_block',
                'name' => 'Encabezado',
                'description' => 'Título de sección con subtítulo opcional.',
                'category' => 'content',
                'icon' => 'heading',
                'feature_gate' => null,
                'sort_order' => 5,
                'schema' => [
                    'fields' => [
                        'text' => ['type' => 'string', 'required' => true, 'label' => 'Título'],
                        'subtitle' => ['type' => 'string', 'required' => false, 'label' => 'Subtítulo'],
                        'level' => ['type' => 'enum', 'options' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], 'default' => 'h2', 'label' => 'Nivel'],
                        'text_align' => ['type' => 'enum', 'options' => ['left', 'center', 'right'], 'default' => 'center', 'label' => 'Alineación'],
                    ],
                    'style_fields' => [
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'subtitle_color' => ['type' => 'color', 'label' => 'Color del subtítulo'],
                    ],
                ],
                'default_content' => [
                    'text' => 'Título de sección',
                    'subtitle' => '',
                    'level' => 'h2',
                    'text_align' => 'center',
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'md',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'image_block',
                'name' => 'Imagen',
                'description' => 'Imagen individual con caption opcional.',
                'category' => 'media',
                'icon' => 'image',
                'feature_gate' => null,
                'sort_order' => 6,
                'schema' => [
                    'fields' => [
                        'image' => ['type' => 'asset', 'required' => true, 'label' => 'Imagen'],
                        'caption' => ['type' => 'string', 'required' => false, 'label' => 'Pie de foto'],
                        'image_fit' => ['type' => 'enum', 'options' => ['cover', 'contain', 'fill'], 'default' => 'cover', 'label' => 'Ajuste'],
                        'max_height' => ['type' => 'number', 'min' => 100, 'max' => 1200, 'default' => 500, 'label' => 'Altura máxima (px)'],
                    ],
                    'style_fields' => [
                        'border_radius' => ['type' => 'enum', 'options' => ['none', 'sm', 'md', 'lg', 'full'], 'label' => 'Bordes redondeados'],
                    ],
                ],
                'default_content' => [
                    'caption' => '',
                    'image_fit' => 'cover',
                    'max_height' => 500,
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'sm',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'image_gallery',
                'name' => 'Galería de Imágenes',
                'description' => 'Grilla de imágenes en columnas configurables.',
                'category' => 'media',
                'icon' => 'layout-grid',
                'feature_gate' => null,
                'sort_order' => 7,
                'schema' => [
                    'fields' => [
                        'images' => ['type' => 'asset_list', 'required' => true, 'label' => 'Imágenes', 'max' => 12],
                        'columns' => ['type' => 'enum', 'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columnas'],
                        'gap' => ['type' => 'enum', 'options' => ['none', 'sm', 'md', 'lg'], 'default' => 'md', 'label' => 'Espaciado'],
                    ],
                    'style_fields' => [
                        'border_radius' => ['type' => 'enum', 'options' => ['none', 'sm', 'md', 'lg'], 'label' => 'Bordes redondeados'],
                    ],
                ],
                'default_content' => [
                    'images' => [],
                    'columns' => '3',
                    'gap' => 'md',
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'md',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'features_grid',
                'name' => 'Grilla de Features',
                'description' => 'Tarjetas con íconos para destacar características o servicios.',
                'category' => 'grids',
                'icon' => 'grid-3x3',
                'feature_gate' => null,
                'sort_order' => 8,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'string', 'required' => false, 'label' => 'Título de sección'],
                        'subheading' => ['type' => 'string', 'required' => false, 'label' => 'Subtítulo'],
                        'items' => ['type' => 'asset_list', 'required' => true, 'label' => 'Features', 'max' => 12],
                        'columns' => ['type' => 'enum', 'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columnas'],
                    ],
                    'style_fields' => [
                        'card_bg' => ['type' => 'color', 'label' => 'Fondo de tarjeta'],
                        'card_radius' => ['type' => 'enum', 'options' => ['none', 'sm', 'md', 'lg'], 'label' => 'Bordes tarjeta'],
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                    ],
                ],
                'default_content' => [
                    'heading' => 'Nuestras características',
                    'subheading' => '',
                    'items' => [
                        ['icon' => 'star', 'title' => 'Calidad', 'description' => 'Lo mejor para ti.'],
                        ['icon' => 'zap', 'title' => 'Rapidez', 'description' => 'Servicio ágil.'],
                        ['icon' => 'shield', 'title' => 'Seguridad', 'description' => 'Tu confianza es prioridad.'],
                    ],
                    'columns' => '3',
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'lg',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'stats_block',
                'name' => 'Estadísticas',
                'description' => 'Números y métricas destacadas.',
                'category' => 'grids',
                'icon' => 'bar-chart-3',
                'feature_gate' => null,
                'sort_order' => 9,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'string', 'required' => false, 'label' => 'Título'],
                        'items' => ['type' => 'asset_list', 'required' => true, 'label' => 'Estadísticas', 'max' => 6],
                        'columns' => ['type' => 'enum', 'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columnas'],
                    ],
                    'style_fields' => [
                        'value_color' => ['type' => 'color', 'label' => 'Color del número'],
                        'label_color' => ['type' => 'color', 'label' => 'Color de la etiqueta'],
                        'value_size' => ['type' => 'enum', 'options' => ['md', 'lg', 'xl', '2xl'], 'label' => 'Tamaño del número'],
                    ],
                ],
                'default_content' => [
                    'heading' => '',
                    'items' => [
                        ['value' => '500+', 'label' => 'Clientes'],
                        ['value' => '10', 'label' => 'Años de experiencia'],
                        ['value' => '99%', 'label' => 'Satisfacción'],
                    ],
                    'columns' => '3',
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'lg',
                    'bg_type' => 'color',
                    'bg_value' => '#f9fafb',
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'cta_banner',
                'name' => 'Call to Action',
                'description' => 'Banner con llamado a la acción destacado.',
                'category' => 'interactive',
                'icon' => 'megaphone',
                'feature_gate' => null,
                'sort_order' => 10,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'rich_text', 'required' => true, 'label' => 'Título'],
                        'body' => ['type' => 'rich_text', 'required' => false, 'label' => 'Descripción'],
                        'cta_text' => ['type' => 'string', 'required' => true, 'label' => 'Texto del botón', 'max' => 50],
                        'cta_url' => ['type' => 'url', 'required' => true, 'label' => 'URL del botón'],
                        'cta_style' => ['type' => 'enum', 'options' => ['solid', 'outline', 'ghost'], 'default' => 'solid', 'label' => 'Estilo del botón'],
                    ],
                    'style_fields' => [
                        'bg_color' => ['type' => 'color', 'label' => 'Color de fondo'],
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'body_color' => ['type' => 'color', 'label' => 'Color del texto'],
                        'cta_bg' => ['type' => 'color', 'label' => 'Color del botón'],
                        'cta_color' => ['type' => 'color', 'label' => 'Color texto botón'],
                    ],
                ],
                'default_content' => [
                    'heading' => '¿Listo para empezar?',
                    'body' => 'Contáctanos hoy y descubre lo que podemos hacer por ti.',
                    'cta_text' => 'Contáctanos',
                    'cta_url' => '#contacto',
                    'cta_style' => 'solid',
                ],
                'default_layout' => [
                    'width' => 'full',
                    'padding_y' => 'lg',
                    'bg_type' => 'color',
                    'bg_value' => '#1e40af',
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'faq_accordion',
                'name' => 'Preguntas Frecuentes',
                'description' => 'Acordeón de preguntas y respuestas.',
                'category' => 'interactive',
                'icon' => 'help-circle',
                'feature_gate' => null,
                'sort_order' => 11,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'string', 'required' => false, 'label' => 'Título'],
                        'items' => ['type' => 'asset_list', 'required' => true, 'label' => 'Preguntas', 'max' => 20],
                    ],
                    'style_fields' => [
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'question_color' => ['type' => 'color', 'label' => 'Color de preguntas'],
                        'answer_color' => ['type' => 'color', 'label' => 'Color de respuestas'],
                    ],
                ],
                'default_content' => [
                    'heading' => 'Preguntas frecuentes',
                    'items' => [
                        ['question' => '¿Cuál es el horario de atención?', 'answer' => 'Lunes a viernes de 9am a 6pm.'],
                        ['question' => '¿Hacen envíos?', 'answer' => 'Sí, realizamos envíos a todo el país.'],
                        ['question' => '¿Cómo puedo contactarlos?', 'answer' => 'A través del formulario de contacto o por teléfono.'],
                    ],
                ],
                'default_layout' => [
                    'width' => 'narrow',
                    'padding_y' => 'lg',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'contact_form',
                'name' => 'Formulario de Contacto',
                'description' => 'Formulario para que los visitantes envíen mensajes.',
                'category' => 'interactive',
                'icon' => 'mail',
                'feature_gate' => null,
                'sort_order' => 12,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'string', 'required' => false, 'label' => 'Título'],
                        'description' => ['type' => 'rich_text', 'required' => false, 'label' => 'Descripción'],
                        'email_to' => ['type' => 'string', 'required' => true, 'label' => 'Email destinatario'],
                        'fields' => ['type' => 'asset_list', 'required' => true, 'label' => 'Campos del formulario', 'max' => 10],
                        'submit_text' => ['type' => 'string', 'required' => false, 'label' => 'Texto del botón', 'max' => 30],
                        'success_message' => ['type' => 'string', 'required' => false, 'label' => 'Mensaje de éxito'],
                    ],
                    'style_fields' => [
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'button_bg' => ['type' => 'color', 'label' => 'Color del botón'],
                        'button_color' => ['type' => 'color', 'label' => 'Color texto botón'],
                    ],
                ],
                'default_content' => [
                    'heading' => 'Contáctanos',
                    'description' => 'Envíanos un mensaje y te responderemos pronto.',
                    'email_to' => '',
                    'fields' => [
                        ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                        ['name' => 'message', 'label' => 'Mensaje', 'type' => 'textarea', 'required' => true],
                    ],
                    'submit_text' => 'Enviar',
                    'success_message' => '¡Gracias! Tu mensaje fue enviado.',
                ],
                'default_layout' => [
                    'width' => 'narrow',
                    'padding_y' => 'lg',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'reservation_widget',
                'name' => 'Widget de Reservas',
                'description' => 'Formulario de reservas interactivo para el sitio web.',
                'category' => 'interactive',
                'icon' => 'calendar',
                'feature_gate' => 'reservations',
                'sort_order' => 13,
                'schema' => [
                    'fields' => [
                        'heading' => ['type' => 'string', 'required' => false, 'label' => 'Título'],
                        'description' => ['type' => 'rich_text', 'required' => false, 'label' => 'Descripción'],
                    ],
                    'style_fields' => [
                        'heading_color' => ['type' => 'color', 'label' => 'Color del título'],
                        'accent_color' => ['type' => 'color', 'label' => 'Color de acento'],
                    ],
                ],
                'default_content' => [
                    'heading' => 'Haz tu reserva',
                    'description' => 'Selecciona fecha, hora y número de personas.',
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'lg',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'spacer',
                'name' => 'Espaciador',
                'description' => 'Espacio vertical configurable entre secciones.',
                'category' => 'content',
                'icon' => 'move-vertical',
                'feature_gate' => null,
                'sort_order' => 14,
                'schema' => [
                    'fields' => [
                        'height' => ['type' => 'enum', 'options' => ['xs', 'sm', 'md', 'lg', 'xl', '2xl'], 'default' => 'md', 'label' => 'Altura'],
                    ],
                    'style_fields' => [],
                ],
                'default_content' => [
                    'height' => 'md',
                ],
                'default_layout' => [
                    'width' => 'full',
                    'padding_y' => 'none',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
            [
                'key' => 'divider',
                'name' => 'Divisor',
                'description' => 'Línea horizontal para separar secciones.',
                'category' => 'content',
                'icon' => 'minus',
                'feature_gate' => null,
                'sort_order' => 15,
                'schema' => [
                    'fields' => [
                        'style' => ['type' => 'enum', 'options' => ['solid', 'dashed', 'dotted'], 'default' => 'solid', 'label' => 'Estilo'],
                        'thickness' => ['type' => 'number', 'min' => 1, 'max' => 8, 'default' => 1, 'label' => 'Grosor (px)'],
                    ],
                    'style_fields' => [
                        'color' => ['type' => 'color', 'label' => 'Color'],
                    ],
                ],
                'default_content' => [
                    'style' => 'solid',
                    'thickness' => 1,
                ],
                'default_layout' => [
                    'width' => 'contained',
                    'padding_y' => 'sm',
                    'bg_type' => 'none',
                    'bg_value' => null,
                    'visibility' => 'all',
                ],
            ],
        ];

        foreach ($blocks as $block) {
            SiteBlockCatalog::query()->updateOrCreate(
                ['key' => $block['key']],
                $block,
            );
        }
    }
}
