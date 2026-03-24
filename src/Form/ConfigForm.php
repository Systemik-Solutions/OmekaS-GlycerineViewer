<?php

namespace GlycerineIIIFViewer\Form;

use Laminas\Form\Form;
use Omeka\Form\Element\PropertySelect;

/**
 * Configuration form for selecting the IIIF manifest property.
 */
class ConfigForm extends Form
{
    /**
     * @return void
     */
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
