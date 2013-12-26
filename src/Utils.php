<?php

namespace PrettyPrinter\Utils {
    class Text {
        /**
         * @param (self[])[] $cells
         *
         * @return \PrettyPrinter\Utils\Text
         */
        static function renderTable(array $rows) {
            $columnWidths = array();

            /** @var $cell Text */
            foreach (self::flipArray($rows) as $colNo => $column) {
                $width = 0;

                foreach ($column as $cell)
                    $width = max($width, $cell->width());

                $columnWidths[$colNo] = $width;
            }

            $result = new Text;

            foreach ($rows as $cells) {
                $row        = new Text;
                $lastColumn = count($cells) - 1;

                foreach ($cells as $column => $cell) {
                    $cell = clone $cell;

                    if ($column !== $lastColumn)
                        $cell->padWidth($columnWidths[$column]);

                    $row->appendLines($cell);
                }

                $result->addLines($row);
            }

            return $result;
        }

        private static function flipArray(array $x) {
            $result = array();

            foreach ($x as $k1 => $v1)
                foreach ($v1 as $k2 => $v2)
                    $result[$k2][$k1] = $v2;

            return $result;
        }

        private $lines, $hasEndingNewLine, $newLineChar;

        function __construct($text = "", $newLineChar = "\n") {
            $this->newLineChar = $newLineChar;
            $this->lines       = explode($this->newLineChar, $text);

            if ($this->hasEndingNewLine = $this->lines[count($this->lines) - 1] === "")
                array_pop($this->lines);
        }

        function toString() {
            $text = join($this->newLineChar, $this->lines);

            if ($this->hasEndingNewLine && $this->lines)
                $text .= $this->newLineChar;

            return $text;
        }

        /**
         * @param string $line
         *
         * @return self
         */
        function addLine($line = "") {
            return $this->addLines(new self($line . $this->newLineChar));
        }

        /**
         * @param Text $add
         *
         * @return self
         */
        function addLines(self $add) {
            foreach ($add->lines as $line)
                $this->lines[] = $line;

            return $this;
        }

        function addLinesBefore(self $addBefore) {
            return $this->addLines($this->swapLines($addBefore));
        }

        function append($string) {
            return $this->appendLines(new self($string));
        }

        /**
         * @param Text $append
         *
         * @return self
         */
        function appendLines(self $append) {
            $space = str_repeat(' ', $this->width());

            foreach ($append->lines as $k => $line)
                if ($k === 0 && $this->lines)
                    $this->lines[count($this->lines) - 1] .= $line;
                else
                    $this->lines[] = $space . $line;

            return $this;
        }

        function count() { return count($this->lines); }

        /**
         * @param int $times
         *
         * @return self
         */
        function indent($times = 1) {
            $space = str_repeat('  ', $times);

            foreach ($this->lines as $k => $line)
                if ($line !== '')
                    $this->lines[$k] = $space . $line;

            return $this;
        }

        function padWidth($width) {
            return $this->append(str_repeat(' ', $width - $this->width()));
        }

        /**
         * @param $string
         *
         * @return self
         */
        function prepend($string) {
            return $this->prependLines(new self($string));
        }

        function prependLine($line = "") {
            return $this->addLines($this->swapLines(new self($line . $this->newLineChar)));
        }

        function prependLines(self $lines) {
            return $this->appendLines($this->swapLines($lines));
        }

        function setHasEndingNewline($value) {
            $this->hasEndingNewLine = (bool)$value;

            return $this;
        }

        function swapLines(self $other) {
            $clone       = clone $this;
            $this->lines = $other->lines;

            return $clone;
        }

        function width() {
            return $this->lines ? strlen($this->lines[count($this->lines) - 1]) : 0;
        }

        function wrap($prepend, $append) {
            return $this->prepend($prepend)->append($append);
        }

        function wrapLines($prepend = '', $append = '') {
            return $this->prependLine($prepend)->addLine($append);
        }
    }
}

