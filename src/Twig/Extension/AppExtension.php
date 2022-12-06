<?php

namespace App\Twig\Extension;

use App\Repository\ConferenceRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private ConferenceRepository $conferenceRepository)
    {
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('conferences', [$this, 'getConferences']),
        ];
    }

    public function getConferences(): array
    {
        return $this->conferenceRepository->findAll();
    }
}
