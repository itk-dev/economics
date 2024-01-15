<?php

namespace App\Service;

use App\Entity\View;
use App\Form\ViewAddStepOneType;
use App\Form\ViewAddStepThreeType;
use App\Form\ViewAddStepTwoType;

class ViewHelperService
{
    /**
     * Supply the multistep information for create form.
     *
     * @return array[]
     */
    public function getCreateFormMultiSteps()
    {
        return [
            1 => [
                'class' => ViewAddStepOneType::class,
                'template' => 'view/addStepOne.html.twig',
            ],
            2 => [
                'class' => ViewAddStepTwoType::class,
                'template' => 'view/addStepTwo.html.twig',
            ],
            3 => [
                'class' => ViewAddStepThreeType::class,
                'template' => 'view/addStepThree.html.twig',
            ],
        ];
    }

    public function getCreateFromInitValues(): array
    {
        return [
            'view' => new View(),
            'current_step' => 1,
        ];
    }
}
