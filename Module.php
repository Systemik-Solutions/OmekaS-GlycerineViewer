<?php

namespace GlycerineIIIFViewer;

use Omeka\Module\AbstractModule;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use GlycerineIIIFViewer\Form\ConfigForm;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;

/**
 * Embeds IIIF manifests in item pages and site blocks using Glycerine Viewer.
 */
class Module extends AbstractModule
{
    /** @var string Default viewer width. */
    const DEFAULT_WIDTH = '100%';

    /** @var string Default viewer height. */
    const DEFAULT_HEIGHT = '600px';

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * @param PhpRenderer $renderer
     * @return string
     */
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

    /**
     * @param AbstractController $controller
     * @return void
     */
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

    /**
     * @param ServiceLocatorInterface $services
     * @return void
     */
    public function install(ServiceLocatorInterface $services): void
    {
        $services
            ->get('Omeka\Settings')
            ->set('glycerine_iiif_manifest_external_property', '');
    }

    /**
     * @param ServiceLocatorInterface $services
     * @return void
     */
    public function uninstall(ServiceLocatorInterface $services): void
    {
        $services
            ->get('Omeka\Settings')
            ->delete('glycerine_iiif_manifest_external_property');
    }

    /**
     * @param MvcEvent $event
     * @return void
     */
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

    /**
     * @param SharedEventManagerInterface $sharedEventManager
     * @return void
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            '*',
            'view.show.after',
            [$this, 'appendIiifIframe']
        );
    }

    /**
     * @param string $s
     * @return bool
     */
    private function isStrictHttpUrl(string $s): bool
    {
        $s = trim($s);
        if (!preg_match('~^https?://~i', $s)) {
            return false;
        }
        return filter_var($s, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param Event $event
     * @return void
     */
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
        if (!$resource) {
            return;
        }

        $services = $view->getHelperPluginManager()->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $propTerm = (string) $settings->get('glycerine_iiif_manifest_external_property', '');
        if ($propTerm === '') {
            return;
        }

        $values = $resource->value($propTerm, ['all' => true, 'default' => []]);

        $manifestUrls = [];
        foreach ((array) $values as $val) {
            $raw = '';
            if ($val instanceof \Omeka\Api\Representation\ValueRepresentation) {
                $raw = $val->uri() ?: $val->value();
            } else {
                $raw = (string) $val;
            }
            // Skip non-URLs
            if ($raw && $this->isStrictHttpUrl($raw)) {
                $manifestUrls[] = $raw;
            }
        }

        if (!$manifestUrls) {
            return;
        }

        $viewerId = 'glycerine-viewer-' . bin2hex(random_bytes(5));

        echo $view->partial('common/glycerine-iiif-inline', [
            'manifestUrls' => $manifestUrls,
            'width' => self::DEFAULT_WIDTH,
            'height' => self::DEFAULT_HEIGHT,
            'viewerId' => $viewerId,
        ]);
    }
}
