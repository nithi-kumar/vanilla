<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\Database\Operation;

use Vanilla\Database\Operation;

/**
 * Database operation processor for including current date fields.
 */
class CurrentDateFieldProcessor implements Processor {

    /** @var array */
    private $insertFields = ["DateInserted"];

    /** @var array */
    private $updateFields = ["DateUpdated"];

    /**
     * Get the list of fields to be populated with the current user ID when adding a new row.
     *
     * @return array
     */
    public function getInsertFields(): array {
        return $this->insertFields;
    }

    /**
     * Get the list of fields to be populated with the current user ID when updating an existing row.
     *
     * @return array
     */
    public function getUpdateFields(): array {
        return $this->updateFields;
    }

    /**
     * Add current date to write operations.
     *
     * @param Operation $databaseOperation
     * @param callable $stack
     * @return mixed
     */
    public function handle(Operation $databaseOperation, callable $stack) {
        switch ($databaseOperation->getType()) {
            case Operation::TYPE_INSERT:
                $fields = $this->getInsertFields();
                break;
            case Operation::TYPE_UPDATE:
                $fields = $this->getUpdateFields();
                break;
            default:
                // Nothing to do here. Shortcut return.
                return $stack($databaseOperation);
        }

        foreach ($fields as $field) {
            $fieldExists = $databaseOperation->getCaller()->getWriteSchema()->getField("properties.{$field}");
            if ($fieldExists) {
                $set = $databaseOperation->getSet();
                if (empty($set[$field] ?? null) || $databaseOperation->getMode() === Operation::MODE_FORCE) {
                    $set[$field] = date("Y-m-d H:i:s");
                } else {
                    if ($set[$field] instanceof \DateTimeImmutable) {
                        $set[$field] = $set[$field]->format("Y-m-d H:i:s");
                    }
                }
                $databaseOperation->setSet($set);
            }
        }

        return $stack($databaseOperation);
    }

    /**
     * Set the list of fields to be populated with the current user ID when adding a new row.
     *
     * @param array $insertFields
     * @return self
     */
    public function setInsertFields(array $insertFields): self {
        $this->insertFields = $insertFields;
        return $this;
    }

    /**
     * Set the list of fields to be populated with the current user ID when updating an existing row.
     *
     * @param array $updateFields
     * @return self
     */
    public function setUpdateFields(array $updateFields): self {
        $this->updateFields = $updateFields;
        return $this;
    }
}
