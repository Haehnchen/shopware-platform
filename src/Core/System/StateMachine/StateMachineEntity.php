<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;

class StateMachineEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var StateMachineTransitionCollection|null
     */
    protected $transitions;

    /**
     * @var StateMachineStateCollection|null
     */
    protected $states;

    /**
     * @var string|null
     */
    protected $initialStateId;

    /**
     * @var StateMachineTranslationCollection
     */
    protected $translations;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTransitions(): ?StateMachineTransitionCollection
    {
        return $this->transitions;
    }

    public function setTransitions(StateMachineTransitionCollection $transitions): void
    {
        $this->transitions = $transitions;
    }

    public function getStates(): ?StateMachineStateCollection
    {
        return $this->states;
    }

    public function setStates(StateMachineStateCollection $states): void
    {
        $this->states = $states;
    }

    public function getInitialState(): ?StateMachineStateEntity
    {
        foreach ($this->states as $state) {
            if ($state->getId() === $this->initialStateId) {
                return $state;
            }
        }

        return null;
    }

    public function getInitialStateId(): ?string
    {
        return $this->initialStateId;
    }

    public function setInitialStateId(string $initialStateId): void
    {
        $this->initialStateId = $initialStateId;
    }

    public function getTranslations(): StateMachineTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(StateMachineTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
