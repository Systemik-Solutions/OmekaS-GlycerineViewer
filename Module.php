<?php

namespace GlycerineIIIFViewer;

use Error;
use Omeka\Module\AbstractModule;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use GlycerineIIIFViewer\Form\ConfigForm;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{

    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }


    public function getConfigForm(PhpRenderer $renderer)
    {
        // Fetch the main container via the view's HelperPluginManager
        $services    = $renderer->getHelperPluginManager()->getServiceLocator();
        $formManager = $services->get('FormElementManager');

        /** @var ConfigForm $form */
        $form = $formManager->get(ConfigForm::class);

        $settings = $services->get('Omeka\Settings');
        $form->setData([
            'glycerine_iiif_manifest_external_property' =>
            $settings->get('glycerine_iiif_manifest_external_property', ''),
        ]);

        return $renderer->formCollection($form);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $request = $controller->getRequest();
        if (!$request->isPost()) {
            return;
        }

        $services    = $controller->getEvent()->getApplication()->getServiceManager();
        $formManager = $services->get('FormElementManager');

        /** @var ConfigForm $form */
        $form  = $formManager->get(ConfigForm::class);
        $data  = $request->getPost()->toArray();
        $form->setData($data);

        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return;
        }

        $values   = $form->getData();
        $settings = $services->get('Omeka\Settings');

        $settings->set(
            'glycerine_iiif_manifest_external_property',
            $values['glycerine_iiif_manifest_external_property'] ?? ''
        );
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $services
            ->get('Omeka\Settings')
            ->set('glycerine_iiif_manifest_external_property', '');
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $services
            ->get('Omeka\Settings')
            ->delete('glycerine_iiif_manifest_external_property');
    }


    public function onBootstrap(MvcEvent $event): void
    {
        // IMPORTANT: ensure base class runs (this is what normally calls attachListeners()).
        parent::onBootstrap($event);

        $services = $event->getApplication()->getServiceManager();
        $viewHelperManager = $services->get('ViewHelperManager');
        $headScript = $viewHelperManager->get('headScript');
        $headLink = $viewHelperManager->get('headLink');

        // Glycerine Viewer
        $headScript->appendFile('https://unpkg.com/glycerine-viewer@latest/jslib/glycerine-viewer.umd.cjs');
        $headLink->appendStylesheet('https://unpkg.com/glycerine-viewer@latest/jslib/style.css');
    }


    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            '*',
            'view.show.after',
            [$this, 'appendIiifIframe']
        );
    }

    public function appendIiifIframe(Event $event): void
    {
        $view = $event->getTarget();

        $resource = $event->getParam('resource');
        if (!$resource) {
            $vars = $view->vars();
            if ($vars->offsetExists('resource')) {
                $resource = $vars->offsetGet('resource');
            } elseif ($vars->offsetExists('item')) {
                $resource = $vars->offsetGet('item');
            }
        }

        if (!$resource) {
            return;
        }

        // Get nominated property & settings.
        $services = $view->getHelperPluginManager()->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $propTerm = (string) $settings->get('glycerine_iiif_manifest_external_property', '');
        if ($propTerm === '') {
            return;
        }

        // Read the manifest URL from the item.
        $manifestUrl = '';
        if ($resource && $propTerm) {
            $val = $resource->value($propTerm, ['all' => false]);

            if ($val instanceof \Omeka\Api\Representation\ValueRepresentation) {
                // If it’s a URI value, use uri(); else value()
                $manifestUrl = $val->uri() ?: $val->value();
            } else {
                $manifestUrl = (string) $val;
            }
        }

        if ($manifestUrl === '') {
            return;
        }

        $width  = (string) $settings->get('giiif_iframe_width', '100%');
        $height = (string) $settings->get('giiif_iframe_height', '600px');

        $viewerId = 'glycerine-viewer-' . bin2hex(random_bytes(5));

        // Inject JS that finds the existing rendered value and REPLACES it with the iframe.
        // It tries a few strategies:
        //  1) Find an <a href="manifestUrl"> and replace its nearest .values/.value container
        //  2) Find a text node equal to the manifestUrl and replace its container
        //  3) If nothing found, do nothing 
        echo '<script>(function(){'
            . 'var manifest=' . json_encode($manifestUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'
            . 'var width='    . json_encode($width,       JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'
            . 'var height='   . json_encode($height,      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'
            . 'var viewerId=' . json_encode($viewerId,    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'

            . 'function replaceAndInit(){'
            // Strategy 1: find <a href="manifest">
            . 'var sel=\'a[href="\'+manifest.replace(/"/g, "\\\\\"")+\'"]\';'
            . 'var a=document.querySelector(sel);'
            . 'var container=null;'
            . 'if(a){container=a.closest(".values")||a.closest(".value")||a.parentElement;}'
            // Strategy 2: find exact text node with manifest
            . 'if(!container){'
            . 'try{'
            . 'var walker=document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {'
            . 'acceptNode:function(n){return (n.nodeValue||"").trim()===manifest?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT;}'
            . '});'
            . 'var node=walker.nextNode();'
            . 'if(node){container=node.parentNode.closest(".values,.value,.property")||node.parentNode;}'
            . '}catch(e){}'
            . '}'
            . 'if(!container){return;}'

            // Replace with viewer div
            . 'container.innerHTML = \'<div id="\'+viewerId+\'"></div>\';'

            // Initialize GlycerineViewer on the new div
            . 'try{'
            . 'var ele=document.getElementById(viewerId);'
            . 'var viewer=new GlycerineViewer(ele,{width:width,height:height,manifest:manifest});'
            . 'viewer.init();'
            . '}catch(e){console.error("GlycerineViewer init error", e);}'
            . '}'
            . '(document.readyState==="loading"?document.addEventListener("DOMContentLoaded",replaceAndInit):replaceAndInit());'
            . '})();</script>';
    }
}
