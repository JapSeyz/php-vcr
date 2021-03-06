<?php

namespace VCR\Storage;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

/**
 * Yaml based storage for records.
 *
 * This storage can be iterated while keeping the memory consumption to the
 * amount of memory used by the largest record.
 */
class Yaml extends AbstractStorage
{
    /**
     * @var Parser Yaml parser.
     */
    protected $yamlParser;

    /**
     * @var  Dumper Yaml writer.
     */
    protected $yamlDumper;

    /**
     * Creates a new YAML based file store.
     *
     * @param string $cassettePath Path to the cassette directory.
     * @param string $cassetteName Path to a file, will be created if not existing.
     * @param Parser $parser Parser used to decode yaml.
     * @param Dumper $dumper Dumper used to encode yaml.
     */
    public function __construct($cassettePath, $cassetteName, Parser $parser = null, Dumper $dumper = null)
    {
        parent::__construct($cassettePath, $cassetteName, '');

        $this->yamlParser = $parser ?? new Parser();
        $this->yamlDumper = $dumper ?? new Dumper();
    }

    /**
     * @inheritDoc
     */
    public function storeRecording(Recording $recording): void
    {
        fseek($this->handle, -1, SEEK_END);
        fwrite($this->handle, "\n" . $this->yamlDumper->dump([$recording->toArray()], 4));
        fflush($this->handle);
    }

    /**
     * Parses the next record.
     *
     * @return void
     */
    public function next(): void
    {
        $recording = $this->yamlParser->parse($this->readNextRecord());
        if (isset($recording[0])) {
            $this->current = new Recording($recording[0]);
        }
        ++$this->position;
    }

    /**
     * Returns the next record in raw format.
     *
     * @return string Next record in raw format.
     */
    private function readNextRecord(): string
    {
        if ($this->isEOF) {
            $this->isValidPosition = false;
        }

        $isInRecord = false;
        $recording = '';

        while (false !== ($line = fgets($this->handle))) {
            $isNewArrayStart = strpos($line, '-') === 0;

            if ($isInRecord && $isNewArrayStart) {
                fseek($this->handle, -strlen($line), SEEK_CUR);
                break;
            }

            if (!$isInRecord && $isNewArrayStart) {
                $isInRecord = true;
            }

            if ($isInRecord) {
                $recording .= $line;
            }
        }

        if ($line === false) {
            $this->isEOF = true;
        }

        return $recording;
    }

    /**
     * Resets the storage to the beginning.
     *
     * @return void
     */
    public function rewind(): void
    {
        rewind($this->handle);
        $this->isEOF = false;
        $this->isValidPosition = true;
        $this->position = 0;
    }

    /**
     * Returns true if the current record is valid.
     *
     * @return boolean True if the current record is valid.
     */
    public function valid(): bool
    {
        if ($this->current === null) {
            $this->next();
        }

        return $this->current !== null && $this->isValidPosition;
    }
}
