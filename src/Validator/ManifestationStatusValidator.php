<?php

namespace App\Validator;

use App\Entity\Manifestation;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Workflow\Registry;

class ManifestationStatusValidator extends ConstraintValidator
{
    public function __construct(private Registry $workflowRegistry)
    {
    }

    /**
     * @param mixed $value
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ManifestationStatus) {
            return;
        }

        if ($value === null || $value === '') {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        $object = $this->context->getObject();
        if (!$object instanceof Manifestation) {
            return;
        }

        $workflow = $this->workflowRegistry->get($object, 'manifestation');
        $places = $workflow->getDefinition()->getPlaces();

        if (!in_array($value, $places, true)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
