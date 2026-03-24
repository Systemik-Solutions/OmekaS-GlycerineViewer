<?php


return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'Glycerine Viewer' => \GlycerineIIIFViewer\Site\BlockLayout\GlycerineIIIFViewer::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            GlycerineIIIFViewer\Form\ConfigForm::class => GlycerineIIIFViewer\Form\ConfigForm::class,
        ],
    ],
];
