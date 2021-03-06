<?php

declare(strict_types=1);

/*
 * This file is part of the ConnectHolland CookieConsentBundle package.
 * (c) Connect Holland.
 */

namespace App\Form;

use ConnectHolland\CookieConsentBundle\Cookie\CookieChecker;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CookieConsentType extends AbstractType
{
    /**
     * @var CookieChecker
     */
    protected CookieChecker $cookieChecker;

    /**
     * @var array
     */
    protected array $cookieCategories;

    public function __construct(CookieChecker $cookieChecker, array $cookieCategories = [
        'analytics',
        'tracking',
        'marketing',
        'social_media'
    ])
    {
        $this->cookieChecker           = $cookieChecker;
        $this->cookieCategories        = $cookieCategories;
    }

    /**
     * Build the cookie consent form.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($this->cookieCategories as $category) {
            $builder->add($category, ChoiceType::class, [
                'expanded' => true,
                'multiple' => false,
                'data'     => $this->cookieChecker->isCategoryAllowedByUser($category) ? 'true' : 'false',
                'choices'  => [
                    ['ch_cookie_consent.yes' => 'true'],
                    ['ch_cookie_consent.no' => 'false'],
                ],
            ]);
        }

        $builder->add(
            'use_all_cookies',
            SubmitType::class,
            ['label' => 'ch_cookie_consent.use_all_cookies', 'attr' => [
                'class' => 'btn ch-cookie-consent__btn'
            ]]
        );
        $builder->add(
            'use_only_functional_cookies',
            SubmitType::class,
            ['label' => 'ch_cookie_consent.use_only_functional_cookies', 'attr' => [
                'class' => 'btn ch-cookie-consent__btn'
            ]]
        );
        $builder->add(
            'save',
            SubmitType::class,
            ['label' => 'ch_cookie_consent.save', 'attr' => [
                'class' => 'btn ch-cookie-consent__btn'
            ]]
        );

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (isset($data['use_all_cookies']) || isset($data['use_only_functional_cookies'])) {
                $value = isset($data['use_all_cookies']) ? 'true' : 'false';
                foreach ($this->cookieCategories as $category) {
                    $data[$category] = $value;
                }
                $event->setData($data);
            }
        });
    }

    /**
     * Default options.
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'CHCookieConsentBundle',
            'allow_extra_fields' => true,
        ]);
    }
}
