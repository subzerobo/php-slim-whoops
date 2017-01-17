<?php
namespace Zeuxisoo\Whoops\Provider\Slim;

use Slim\App as SlimApp;

use Whoops\Util\Misc;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;

use Psr\Http\Message\ServerRequestInterface;

class WhoopsGuard {

    private $app      = null;
    private $handlers = [];

    public function setApp(SlimApp $app) {
        $this->app = $app;
    }

    public function setRequest(ServerRequestInterface $request) {
        $this->request = $request;
    }

    public function setHandlers(array $handlers) {
        $this->handlers = $handlers;
    }

    public function install() {
        $container   = $this->app->getContainer();
        $settings    = $container['settings'];
        $environment = $container['environment'];

        if (isset($settings['debug']) === true && $settings['debug'] === true) {
            // Enable PrettyPageHandler with editor options
            $prettyPageHandler = new PrettyPageHandler();

            if (empty($settings['whoops.editor']) === false) {
                $prettyPageHandler->setEditor($settings['whoops.editor']);
            }

            // Add more information to the PrettyPageHandler
            $prettyPageHandler->addDataTable('Slim Application', [
                'Application Class' => get_class($this->app),
                'Script Name'       => $environment->get('SCRIPT_NAME'),
                'Request URI'       => $environment->get('PATH_INFO') ?: '<none>',
            ]);

            $prettyPageHandler->addDataTable('Slim Application (Request)', array(
                'Accept Charset'  => $this->request->getHeader('ACCEPT_CHARSET') ?: '<none>',
                'Content Charset' => $this->request->getContentCharset() ?: '<none>',
                'Path'            => $this->request->getUri()->getPath(),
                'Query String'    => $this->request->getUri()->getQuery() ?: '<none>',
                'HTTP Method'     => $this->request->getMethod(),
                'Base URL'        => (string) $this->request->getUri(),
                'Scheme'          => $this->request->getUri()->getScheme(),
                'Port'            => $this->request->getUri()->getPort(),
                'Host'            => $this->request->getUri()->getHost(),
            ));

            // Set Whoops to default exception handler
            $whoops = new \Whoops\Run;
            $whoops->pushHandler($prettyPageHandler);

            // Enable JsonResponseHandler when request is AJAX
            if (Misc::isAjaxRequest()){
                $whoops->pushHandler(new JsonResponseHandler());
            }

            // Add each custom handler to whoops handler stack
            if (empty($this->handlers) === false) {
                foreach($this->handlers as $handler) {
                    $whoops->pushHandler($handler);
                }
            }

            $whoops->register();

            $container['phpErrorHandler'] = $container['errorHandler'] = function() use ($whoops) {
                return new WhoopsErrorHandler($whoops);
            };

            $container['whoops'] = $whoops;
        }
    }

}
