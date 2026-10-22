<?php
namespace CreareServiceAreaElements;

use function Breakdance\Elements\c;
use function Breakdance\Elements\PresetSections\getPresetSection;

\Breakdance\ElementStudio\registerElementForEditing(__NAMESPACE__ . '\\ServiceAreaContent', \Breakdance\Util\getDirectoryPathRelativeToPluginFolder(__DIR__));

class ServiceAreaContent extends \Breakdance\Elements\Element
{
    public static function name() { return 'Service Area Content'; }
    public static function className() { return 'creare-service-area-content'; }
    public static function slug() { return __CLASS__; }
    public static function category() { return 'other'; }
    public static function template() { return file_get_contents(__DIR__ . '/html.twig'); }
    public static function defaultCss() { return file_get_contents(__DIR__ . '/default.css'); }
    public static function cssTemplate() { return file_get_contents(__DIR__ . '/css.twig'); }
    public static function defaultProperties()
    {
        return ['content' => ['display' => ['show_heading' => true, 'show_content' => true, 'show_faqs' => true, 'show_contact' => true]]];
    }
    public static function contentControls()
    {
        $controls = [c('area_id', 'Preview / fixed service area ID', [], ['type' => 'number'], false, false, [])];
        foreach (['show_heading' => 'Show page heading', 'show_content' => 'Show service information', 'show_faqs' => 'Show FAQs', 'show_contact' => 'Show contact section'] as $key => $label) {
            $controls[] = c($key, $label, [], ['type' => 'toggle'], false, false, []);
        }
        $controls[] = c('faq_heading', 'FAQ heading override', [], ['type' => 'text'], false, false, []);
        return [c('display', 'Display', $controls, ['type' => 'section'], false, false, [])];
    }
    public static function designControls()
    {
        $sections = [];
        foreach (['heading' => 'Page Heading', 'body' => 'Service Information', 'faq' => 'FAQ Questions', 'contact' => 'Contact Heading', 'button' => 'Booking Button'] as $key => $label) {
            $sections[] = c($key, $label, [getPresetSection('EssentialElements\\typography', 'Typography', 'typography', ['type' => 'popout'])], ['type' => 'section'], false, false, []);
        }
        $sections[] = c('layout', 'Layout', [
            c('max_width', 'Maximum Width', [], ['type' => 'unit', 'layout' => 'inline', 'rangeOptions' => ['min' => 0]], false, false, []),
            c('section_gap', 'Section Gap', [], ['type' => 'unit', 'layout' => 'inline', 'rangeOptions' => ['min' => 0]], false, false, []),
            getPresetSection('EssentialElements\\spacing_padding_all', 'Padding', 'padding', ['type' => 'popout']),
        ], ['type' => 'section'], false, false, []);
        return $sections;
    }
    public static function propertyPathsToSsrElementWhenValueChanges()
    {
        return ['content.display'];
    }
}
