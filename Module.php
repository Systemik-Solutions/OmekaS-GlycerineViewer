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

    function isStrictHttpUrl(string $s): bool
    {
        $s = trim($s);
        if (!preg_match('~^https?://~i', $s)) return false;        
        return filter_var($s, FILTER_VALIDATE_URL) !== false;      
    }

    public function appendIiifIframe(Event $event): void
    {
        $view = $event->getTarget();

        $resource = $event->getParam('resource');
        if (!$resource) {
            $vars = $view->vars();
            $resource = $vars->offsetExists('resource')
                ? $vars->offsetGet('resource')
                : ($vars->offsetExists('item') ? $vars->offsetGet('item') : null);
        }
        if (!$resource) return;


        $services = $view->getHelperPluginManager()->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $propTerm = (string) $settings->get('glycerine_iiif_manifest_external_property', '');
        if ($propTerm === '') return;


        $values = $resource->value($propTerm, ['all' => true, 'default' => []]);

        $manifestUrls = [];
        foreach ((array) $values as $val) {
            $raw = '';
            if ($val instanceof \Omeka\Api\Representation\ValueRepresentation) {
                $raw = $val->uri() ?: $val->value();
            } else {
                $raw = (string) $val;
            }
            // skip non-URLs =
            if ($raw && $this->isStrictHttpUrl($raw)) {
                $manifestUrls[] = $raw;
            }
        }

        // If nothing usable, do nothing
        if (!$manifestUrls) return;

        $width  = (string) $settings->get('giiif_iframe_width', '100%');
        $height = (string) $settings->get('giiif_iframe_height', '600px');

        $viewerId = 'glycerine-viewer-' . bin2hex(random_bytes(5));

        // Inject JS that finds the existing rendered value and REPLACES it with the iframe.
        // It tries a few strategies:
        //  1) Find an <a href="manifestUrl"> and replace its nearest .values/.value container
        //  2) Find a text node equal to the manifestUrl and replace its container
        //  3) If nothing found, do nothing 
        echo '<script>(function(){'
            . 'var manifests=' . json_encode($manifestUrls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'
            . 'var width='    . json_encode($width,  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'
            . 'var height='   . json_encode($height, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'
            . 'var viewerId=' . json_encode($viewerId, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'

            . 'function replaceAndInit(){'
            . 'manifests.forEach(function(m){'
            // find <a href="m"> and replace ONLY its .value container
            . '  var sel=\'a[href="\'+String(m).replace(/"/g, "\\\\\"")+\'"]\';'
            . '  document.querySelectorAll(sel).forEach(function(a){'
            . '    var valueEl=a.closest(".value")||a.parentElement;'
            . '    if(!valueEl) return;'
            . '    var id=viewerId+"-"+Math.random().toString(36).slice(2);'
            . '    valueEl.innerHTML = \'<div id="\'+id+\'"></div>\';'
            . '    try{var ele=document.getElementById(id);var viewer=new GlycerineViewer(ele,{width:width,height:height,manifest:m});viewer.init();}catch(e){console.error("GlycerineViewer init error", e);}'
            . '  });'
            // find exact text nodes == m and replace ONLY that .value block
            . '  try{'
            . '    var walker=document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {acceptNode:function(n){return (n.nodeValue||"").trim()===String(m)?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT;}});'
            . '    var node;'
            . '    while(node=walker.nextNode()){'
            . '      var valueEl=(node.parentNode && node.parentNode.closest && node.parentNode.closest(".value"))||node.parentNode;'
            . '      if(!valueEl) continue;'
            . '      var id=viewerId+"-"+Math.random().toString(36).slice(2);'
            . '      valueEl.innerHTML = \'<div id="\'+id+\'"></div>\';'
            . '      try{var ele=document.getElementById(id);var viewer=new GlycerineViewer(ele,{width:width,height:height,manifest:m});viewer.init();}catch(e){console.error("GlycerineViewer init error", e);}'
            . '    }'
            . '  }catch(e){}'
            . '});'
            . '}'
            . '(document.readyState==="loading"?document.addEventListener("DOMContentLoaded",replaceAndInit):replaceAndInit());'
            . '})();</script>';
    }
}
