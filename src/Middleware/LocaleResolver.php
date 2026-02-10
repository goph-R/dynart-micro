<?php

namespace Dynart\Micro\Middleware;

use Dynart\Micro\MiddlewareInterface;
use Dynart\Micro\RequestInterface;
use Dynart\Micro\RouterInterface;
use Dynart\Micro\TranslationInterface;

class LocaleResolver implements MiddlewareInterface {

    protected int $localeRouteSegment;

    public function __construct(
        protected RequestInterface $request,
        protected RouterInterface $router,
        protected TranslationInterface $translation
    ) {}

    public function run(): void {
        if (!$this->translation->hasMultiLocales()) {
            return;
        }
        $this->localeRouteSegment = $this->router->addPrefixVariable([$this->translation, 'locale']);
        $this->setLocaleViaAcceptLanguage();
        $this->setLocaleViaParameter();
    }

    protected function setLocaleViaAcceptLanguage(): void {
        $acceptLanguage = $this->request->server('HTTP_ACCEPT_LANGUAGE');
        if ($acceptLanguage) {
            $acceptLocale = strtolower(substr($acceptLanguage, 0, 2)); // we use only neutral locale for now
            if (in_array($acceptLocale, $this->translation->allLocales())) {
                $this->translation->setLocale($acceptLocale);
            }
        }
    }

    protected function setLocaleViaParameter(): void {
        $locale = $this->router->currentSegment($this->localeRouteSegment);
        if (in_array($locale, $this->translation->allLocales())) {
            $this->translation->setLocale($locale);
        }
    }

}