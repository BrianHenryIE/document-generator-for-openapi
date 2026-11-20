<?php

namespace BrianHenryIE\OpenAPIGenerator;

/**
 * Two different schemas would be extracted to `components.schemas` under one name.
 *
 * The generator used to rename the second `name_1`. A name is given with the schema's `title`, so the fix is to
 * give the two schemas different titles, or to make them the same schema.
 */
class ComponentNameCollisionException extends \RuntimeException {

    /** @var string */
    protected $componentName;

    /** @var string */
    protected $firstLocation;

    /** @var string */
    protected $secondLocation;

    public function __construct( $componentName, $firstLocation, $secondLocation ) {
        $this->componentName  = $componentName;
        $this->firstLocation  = $firstLocation;
        $this->secondLocation = $secondLocation;

        parent::__construct(
            sprintf(
                'The component "%1$s" has two different schemas: at %2$s and at %3$s. Give them different titles, or make them the same.',
                $componentName,
                $firstLocation,
                $secondLocation
            )
        );
    }

    public function getComponentName() {
        return $this->componentName;
    }

    public function getFirstLocation() {
        return $this->firstLocation;
    }

    public function getSecondLocation() {
        return $this->secondLocation;
    }
}
