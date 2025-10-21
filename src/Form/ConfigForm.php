<?php 
namespace GlycerineIIIFViewer\Form;

use Laminas\Form\Form;
use Omeka\Form\Element\PropertySelect;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'glycerine_iiif_manifest_external_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Choose property supplying an IIIF manifest', 
                    'empty_option' => '',
                    'term_as_value' => true,
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'glycerine_iiif_manifest_external_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', 
                ],
            ]);
    }
}
