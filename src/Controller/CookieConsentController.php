<?php

declare(strict_types=1);

/*
 * This file is part of the ConnectHolland CookieConsentBundle package.
 * (c) Connect Holland.
 */

namespace App\Controller;

use ConnectHolland\CookieConsentBundle\Cookie\CookieChecker;
use App\Form\CookieConsentType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use ConnectHolland\CookieConsentBundle\Controller\CookieConsentController as BaseController;

class CookieConsentController extends BaseController
{
    /**
     * @var Environment
     */
    protected $twigEnvironment;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var CookieChecker
     */
    protected $cookieChecker;

    /**
     * @var string
     */
    protected $cookConsTheme = 'dark';

    /**
     * @var string
     */
    protected $cookConsPosition = 'bottom';

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var bool
     */
    protected $cookConsSimplified = true;

    public function __construct(
        Environment $twigEnvironment,
        FormFactoryInterface $formFactory,
        CookieChecker $cookieChecker,
        TranslatorInterface $translator,
        bool $cookConsSimplified,
        string $cookConsTheme,
        string $cookConsPosition
    ) {
        $this->twigEnvironment         = $twigEnvironment;
        $this->formFactory             = $formFactory;
        $this->cookieChecker           = $cookieChecker;
        $this->cookConsTheme      = $cookConsTheme;
        $this->cookConsPosition   = $cookConsPosition;
        $this->translator              = $translator;
        $this->cookConsSimplified = $cookConsSimplified;
    }

    /**
     * Show cookie consent.
     *
     * @Route("/cookie_consent", name="ch_cookie_consent.show")
     */
    public function show(Request $request): Response
    {
        $this->setLocale($request);

        return new Response(
            $this->twigEnvironment->render('@CHCookieConsent/cookie_consent.html.twig', [
                'form'       => $this->createCookieConsentForm()->createView(),
                'theme'      => $this->cookConsTheme,
                'position'   => $this->cookConsPosition,
                'simplified' => $this->cookConsSimplified,
            ])
        );
    }

    /**
     * Show cookie consent.
     *
     * @Route("/cookie_consent_alt", name="ch_cookie_consent.show_if_cookie_consent_not_set")
     */
    public function showIfCookieConsentNotSet(Request $request): Response
    {
        if ($this->cookieChecker->isCookieConsentSavedByUser() === false) {
            return $this->show($request);
        }

        return new Response();
    }

    /**
     * Create cookie consent form.
     */
    protected function createCookieConsentForm(): FormInterface
    {
        return $this->formFactory->create(CookieConsentType::class);
    }

    /**
     * Set locale if available as GET parameter.
     * @param Request $request
     */
    protected function setLocale(Request $request): void
    {
        $locale = $request->get('locale');
        if (empty($locale) === false) {
            /* @phpstan-ignore-next-line */
            $this->translator->setLocale($locale);
            $request->setLocale($locale);
        }
    }
}
