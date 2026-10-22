<?php
defined('ABSPATH') || exit;

$display = $propertiesData['content']['display'] ?? [];
$area_id = !empty($display['area_id']) ? absint($display['area_id']) : get_the_ID();
echo \Creare\ServiceAreas\Renderer::render((int) $area_id, $display);
