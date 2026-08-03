<?php
/**
 * Bloco da Clínica para o functions.php do tema.
 *
 * Os templates já fazem require dos includes, mas deixá-los aqui garante
 * que estão disponíveis em qualquer outro sítio (ex.: um shortcode futuro).
 */

// Camada de dados + controladores/apresentação.
require_once get_stylesheet_directory() . '/inc/clinic-repo.php';
require_once get_stylesheet_directory() . '/inc/clinic-ui.php';
