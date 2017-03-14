<?php
namespace Cyberhouse\DoctrineORM\Migration;

/*
 * This file is (c) 2017 by Cyberhouse GmbH
 *
 * It is free software; you can redistribute it and/or
 * modify it under the terms of the GPLv3 license
 *
 * For the full copyright and license information see
 * <https://www.gnu.org/licenses/gpl-3.0.html>
 */

use Cyberhouse\DoctrineORM\Database\CreateTablePrinter;
use Cyberhouse\DoctrineORM\Database\IdentifierQuotes;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Schema\Exception\StatementException;
use TYPO3\CMS\Core\Database\Schema\Parser\Parser;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Merge a given schema with the schema of an entity manager
 *
 * @author Georg Großberger <georg.grossberger@cyberhouse.at>
 */
class MigrationMerger
{
    /**
     * @inject
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var array|[]string
     */
    private $result = [];

    /**
     * @var IdentifierQuotes
     */
    private $quotes;

    public function __construct()
    {
        $this->quotes = GeneralUtility::makeInstance(IdentifierQuotes::class);
    }

    /**
     * MigrationMerger constructor.
     *
     * @param array $source
     */
    public function initialize(array $source)
    {
        $tables = [];
        $reader = $this->objectManager->get(SqlReader::class);

        foreach ($source as $statement) {
            $creates = $reader->getCreateTableStatementArray($statement);

            foreach ($creates as $createStatement) {
                $parser = $this->objectManager->get(Parser::class, $createStatement);

                try {
                    /** @var Table $table */
                    foreach ($parser->parse() as $table) {
                        $name = $this->quotes->remove($table->getName());

                        if (isset($tables[$name])) {
                            $table = $this->mergeTables($tables[$name], $table);
                        }

                        $tables[$name] = $table;
                    }
                } catch (StatementException $ex) {
                    throw new StatementException(
                        $ex->getMessage() . ' in statement: ' . LF . $createStatement,
                        1476171315,
                        $ex
                    );
                }
            }
        }

        $this->schema = $this->objectManager->get(Schema::class, array_values($tables));
    }

    public function mergeWith(EntityManager $em, string $extension)
    {
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = $this->objectManager->get(SchemaTool::class, $em);
        $additional = $schemaTool->getSchemaFromMetadata($metadata);

        $tables = [];
        $namespaces = [];

        /** @var Schema $schema */
        foreach ([$additional, $this->schema] as $schema) {
            $namespaces = array_merge($namespaces, $schema->getNamespaces());

            foreach ($schema->getTables() as $table) {
                $name = $this->quotes->remove($table->getName());

                if (isset($tables[$name])) {
                    $table = $this->mergeTables($table, $tables[$name]);
                }

                $tables[$name] = $table;
            }
        }

        $config = $em->getConnection()->getSchemaManager()->createSchemaConfig();
        $config->setName($this->schema->getName());

        $this->schema = $this->objectManager->get(
            Schema::class,
            array_values($tables),
            [],
            $config,
            array_unique($namespaces)
        );

        $platform = $this->objectManager
            ->get(ConnectionPool::class)
            ->getConnectionForTable($extension)
            ->getDatabasePlatform();

        $creates = [];
        $printer = $this->objectManager->get(CreateTablePrinter::class);

        foreach ($this->schema->toSql($platform) as $statement) {
            if (StringUtility::beginsWith($statement, 'CREATE TABLE ')) {
                $name = $this->quotes->remove(substr($statement, 13, stripos($statement, ' ', 13) - 13));

                if (isset($creates[$name])) {
                    throw new \UnexpectedValueException('Several create statements for table ' . $name . ' present');
                }

                $creates[$name] = $printer->getStatement($statement, false);
            }
        }

        $this->result = array_values($creates);
    }

    public function getResult()
    {
        $result = array_map(function ($entry) {
            return $entry . ';' . LF;
        }, $this->result);
        return $result;
    }

    protected function mergeTables(Table $a, Table $b): Table
    {
        $data = [
            'columns'       => [],
            'indexes'       => [],
            'fkConstraints' => [],
            'options'       => $a->getOptions(),
        ];

        /** @var Table $table */
        foreach ([$a, $b] as $table) {
            foreach ($table->getColumns() as $column) {
                if (!isset($data['columns'][$this->quotes->remove($column->getName())])) {
                    $data['columns'][$this->quotes->remove($column->getName())] = $column;
                }
            }

            foreach ($table->getIndexes() as $index) {
                if (!isset($data['indexes'][$this->quotes->remove($index->getName())])) {
                    $data['indexes'][$this->quotes->remove($index->getName())] = $index;
                }
            }

            foreach ($table->getForeignKeys() as $fk) {
                if (!isset($data['fkConstraints'][$this->quotes->remove($fk->getName())])) {
                    $data['fkConstraints'][$this->quotes->remove($fk->getName())] = $fk;
                }
            }
        }

        return $this->objectManager->get(
            Table::class,
            $a->getName(),
            array_values($data['columns']),
            array_values($data['indexes']),
            array_values($data['fkConstraints']),
            0,
            $data['options']
        );
    }
}
