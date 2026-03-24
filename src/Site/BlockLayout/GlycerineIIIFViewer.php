<?php

namespace GlycerineIIIFViewer\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Laminas\View\Renderer\PhpRenderer;

/**
 * Block layout for embedding a Glycerine IIIF Viewer on site pages.
 */
class GlycerineIIIFViewer extends AbstractBlockLayout
{
    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Glycerine IIIF Viewer';
    }

    /**
     * @param PhpRenderer $view
     * @param SiteRepresentation $site
     * @param SitePageRepresentation|null $page
     * @param SitePageBlockRepresentation|null $block
     * @return string
     */
    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        ?SitePageRepresentation $page = null,
        ?SitePageBlockRepresentation $block = null
    ) {
        $iiifManifestUrl = '';
        $width = '100%';
        $height = '600px';
        if ($block) {
            $data = $block->data();
            $iiifManifestUrl = $data['iiifManifestUrl'] ?? '';
            $width = $data['width'] ?? '100%';
            $height = $data['height'] ?? '600px';
        }

        $escapedUrl = $view->escapeHtmlAttr($iiifManifestUrl);
        $escapedWidth = $view->escapeHtmlAttr($width);
        $escapedHeight = $view->escapeHtmlAttr($height);

        return <<<HTML
            <div class="form-group">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <label style="min-width: 30%;">IIIF Manifest URL</label>
                    <input style="min-width: 65%;" type="text"
                        name="o:block[__blockIndex__][o:data][iiifManifestUrl]"
                        value="{$escapedUrl}">
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <label style="min-width: 30%;">Width</label>
                    <input style="min-width: 65%;" type="text"
                        name="o:block[__blockIndex__][o:data][width]"
                        value="{$escapedWidth}">
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <label style="min-width: 30%;">Height</label>
                    <input style="min-width: 65%;" type="text"
                        name="o:block[__blockIndex__][o:data][height]"
                        value="{$escapedHeight}">
                </div>
            </div>
            HTML;
    }

    /**
     * @param PhpRenderer $view
     * @param SitePageBlockRepresentation $block
     * @return string
     */
    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return $view->partial('common/block/GlycerineIIIFViewer', [
            'block' => $block,
            'query' => $block->dataValue('query'),
        ]);
    }
}