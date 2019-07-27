<?php namespace DCarbone\PHPFHIR;

/*
 * Copyright 2016-2019 Daniel Carbone (daniel.p.carbone@gmail.com)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use DCarbone\PHPFHIR\Config\VersionConfig;
use DCarbone\PHPFHIR\Generator\TemplateBuilder;
use DCarbone\PHPFHIR\Utilities\CopyrightUtils;
use DCarbone\PHPFHIR\Utilities\FileUtils;

/**
 * Class Builder
 * @package DCarbone\PHPFHIR
 */
class Builder
{
    /** @var \DCarbone\PHPFHIR\Config\VersionConfig */
    protected $config;

    /** @var \DCarbone\PHPFHIR\Definition */
    protected $definition;

    /** @var \DCarbone\PHPFHIR\Logger */
    private $log;

    /**
     * Generator constructor.
     * @param \DCarbone\PHPFHIR\Config\VersionConfig $config
     * @param \DCarbone\PHPFHIR\Definition $definition
     */
    public function __construct(VersionConfig $config, Definition $definition)
    {
        $this->config = $config;
        $this->definition = $definition;
        $this->log = $config->getLogger();
    }

    /**
     * Generate FHIR object classes based on XSD
     */
    public function build()
    {
        $this->beforeGeneration();

        if (!$this->definition->isDefined()) {
            $this->config->getLogger()->startBreak('XSD Parsing');
            $this->definition->buildDefinition();
            $this->config->getLogger()->endBreak('XSD Parsing');
        }

        $types = $this->definition->getTypes();

        foreach ($types->getIterator() as $type) {
            if ($types->isContainedType($type)) {
                $type->setContainedType(true);
            }
        }

        $this->config->getLogger()->startBreak('Class Generation');
        foreach ($types->getIterator() as $type) {
            $this->config->getLogger()->debug("Generating class for element {$type}...");
            $classDefinition = TemplateBuilder::generateTypeClass($this->config, $this->definition->getTypes(), $type);
            if (null !== $classDefinition) {
                if (!(bool)file_put_contents(FileUtils::buildTypeFilePath($this->config, $type), $classDefinition)) {
                    throw new \RuntimeException(sprintf(
                        'Unable to write Type %s',
                        $type
                    ));
                }
            }
        }
        $this->config->getLogger()->endBreak('Class Generation');

        $this->afterGeneration();
    }

    /**
     * Commands to run prior to class generation
     */
    protected function beforeGeneration()
    {
        // Initialize some classes and things.
        $this->log->startBreak('Generator Class Initialization');
        $this->log->info('Compiling Copyrights...');
        CopyrightUtils::compileCopyrights($this->config);
        $this->log->endBreak('Generator Class Initialization');
    }

    /**
     * @param string $filePath
     * @param string $fileContents
     */
    private function writeClassFile($filePath, $fileContents)
    {
        $this->log->info(sprintf('Writing %s...', $filePath));
        $b = file_put_contents($filePath, $fileContents);
        if (false === $b) {
            throw new \RuntimeException(sprintf(
                'Unable to write "%s"',
                $filePath
            ));
        }
        $this->log->debug(sprintf('%d bytes written to file %s', $b, $filePath));
    }

    /**
     * Commands to run after class generation
     */
    protected function afterGeneration()
    {
        $types = $this->definition->getTypes();

        $this->writeClassFile(
            FileUtils::buildGenericFilePath(
                $this->config,
                $this->config->getNamespace(true),
                PHPFHIR_CLASSNAME_CONSTANTS
            ),
            TemplateBuilder::generateConstants($this->config, $types)
        );

        $this->writeClassFile(
            FileUtils::buildGenericFilePath(
                $this->config,
                $this->config->getNamespace(true),
                PHPFHIR_CLASSNAME_TYPEMAP
            ),
            TemplateBuilder::generateTypeMapClass($this->config, $types)
        );

        $this->writeClassFile(
            FileUtils::buildGenericFilePath(
                $this->config,
                $this->config->getNamespace(true),
                PHPFHIR_CLASSNAME_AUTOLOADER
            ),
            TemplateBuilder::generateAutoloaderClass($this->config, $types)
        );

        $this->writeClassFile(
            FileUtils::buildGenericFilePath(
                $this->config,
                $this->config->getNamespace(true),
                PHPFHIR_INTERFACE_TYPE
            ),
            TemplateBuilder::generatePHPFHIRTypeInterface($this->config, $types)
        );

        $this->writeClassFile(
            FileUtils::buildGenericFilePath(
                $this->config,
                $this->config->getNamespace(true),
                PHPFHIR_INTERFACE_CONTAINED_TYPE
            ),
            TemplateBuilder::generatePHPFHIRContainedTypeInterface($this->config, $types)
        );

//        $this->log->info('Writing Autoloader...');
//        $autoloaderFilePath = FileUtils::buildGenericClassFilePath(
//            $this->config,
//            $this->config->getNamespace(true),
//            'PHPFHIRAutoloader'
//        );
//        if (!(bool)file_put_contents(
//            $autoloaderFilePath,
//            AutoloaderUtils::build($this->config, $this->definition))) {
//            throw new \RuntimeException("Unable to write autoloader to path: {$autoloaderFilePath}");
//        }
//
//        $this->log->info('Writing ResponseParser...');
//        $parserFilePath = FileUtils::buildGenericClassFilePath(
//            $this->config,
//            $this->config->getNamespace(true),
//            'PHPFHIRResponseParser'
//        );
//        if (!(bool)file_put_contents(
//            $parserFilePath,
//            ResponseParserUtils::build($this->config))) {
//            throw new \RuntimeException("Unable to write response parser to path: {$parserFilePath}");
//        }
//
//        $this->log->info('Writing TypeMap...');
//        $typeMapFilePath = FileUtils::buildGenericClassFilePath(
//            $this->config,
//            $this->config->getNamespace(true),
//            'PHPFHIRTypeMap'
//        );
//        if (!(bool)file_put_contents(
//            $typeMapFilePath,
//            TypeMapUtils::build($this->config, $this->definition))) {
//            throw new \RuntimeException("Unable to write response parser to path: {$typeMapFilePath}");
//        }
    }
}
