<?php

namespace Dynart\Micro\I18n;

use Dynart\Micro\Middleware;
use Dynart\Micro\Request;
use Dynart\Micro\Router;

class LocaleResolver implements Middleware {

    /** @var Request */
    protected $request;

    /** @var Router */
    protected $router;

    /** @var Translation */
    protected $translation;

    protected $parameterIndex;

    public function __construct(Request $request, Router $router, Translation $translation) {
        $this->translation = $translation;
        $this->request = $request;
        $this->router = $router;
    }

    public function run() {
        if (!$this->translation->hasMultiLocales()) {
            return;
        }
        $this->setLocaleViaAcceptLanguage();
        $this->setLocaleViaParameter();
        $this->parameterIndex = $this->router->addPrefixVariable([$this->translation, 'locale']);
    }

    public function setLocaleViaAcceptLanguage() {
        $acceptLanguage = $this->request->server('HTTP_ACCEPT_LANGUAGE');
        if ($acceptLanguage) {
            $acceptLocale = strtolower(substr($acceptLanguage, 0, 2)); // we use only neutral locale for now
            if (in_array($acceptLocale, $this->translation->allLocales())) {
                $this->translation->setLocale($acceptLocale);
            }
        }
    }

    public function setLocaleViaParameter() {
        $locale = $this->router->currentSegment($this->parameterIndex);
        if (in_array($locale, $this->translation->allLocales())) {
            $this->translation->setLocale($locale);
        }
    }

}