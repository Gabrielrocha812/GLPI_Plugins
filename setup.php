<?php

function plugin_init_relatoriotecnicos() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['relatoriotecnicos'] = true;

    $PLUGIN_HOOKS['menu_toadd']['relatoriotecnicos'] = [
        'tools' => 'PluginRelatoriotecnicosRelatoriotecnicos' // JÁ ESTÁ CORRETO
    ];

    // JÁ ESTÁ CORRETO
    Plugin::registerClass('PluginRelatoriotecnicosRelatoriotecnicos', [
        'rights' => [
            READ => __('Ver relatório de horas', 'relatoriotecnicos')
        ]
    ]);
}

// As demais funções estão corretas
function plugin_version_relatoriotecnicos() {
    return [
        'name'           => 'Relatório de Horas para Técnicos',
        'version'        => '1.0.0',
        'author'         => 'Gabriel Rocha',
        'license'        => 'GPLv2+',
        'homepage'       => '',
        'minGlpiVersion' => '9.5'
    ];
}

function plugin_relatoriotecnicos_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '9.5', 'lt')) {
        echo "Este plugin requer GLPI >= 9.5";
        return false;
    }
    return true;
}

function plugin_relatoriotecnicos_check_config($verbose = false) {
    return true;
}

function plugin_relatoriotecnicos_install() {
    return true;
}

function plugin_relatoriotecnicos_uninstall() {
    return true;
}
?>