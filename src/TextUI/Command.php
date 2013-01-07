<?php
/**
 * hphpa
 *
 * Copyright (c) 2012-2013, Sebastian Bergmann <sebastian@phpunit.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package   hphpa
 * @author    Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright 2012-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @since     File available since Release 1.0.0
 */

namespace SebastianBergmann\HPHPA\TextUI
{
    use SebastianBergmann\FinderFacade\FinderFacade;
    use SebastianBergmann\HPHPA\Analyzer;
    use SebastianBergmann\HPHPA\Result;
    use SebastianBergmann\HPHPA\Ruleset;
    use SebastianBergmann\HPHPA\Report\Checkstyle;
    use SebastianBergmann\HPHPA\Report\Text;
    use SebastianBergmann\HPHPA\Version;

    /**
     * TextUI frontend.
     *
     * @author    Sebastian Bergmann <sebastian@phpunit.de>
     * @copyright 2012-2013 Sebastian Bergmann <sebastian@phpunit.de>
     * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
     * @link      http://github.com/sebastianbergmann/hphpa/tree
     * @since     Class available since Release 1.0.0
     */
    class Command
    {
        /**
         * Main method.
         */
        public function main()
        {
            $input = new \ezcConsoleInput;

            $input->registerOption(
              new \ezcConsoleOption(
                '',
                'checkstyle',
                \ezcConsoleInput::TYPE_STRING
               )
            );

            $input->registerOption(
              new \ezcConsoleOption(
                '',
                'ruleset',
                \ezcConsoleInput::TYPE_STRING
               )
            );

            $input->registerOption(
              new \ezcConsoleOption(
                '',
                'exclude',
                \ezcConsoleInput::TYPE_STRING,
                array(),
                TRUE
               )
            );

            $input->registerOption(
              new \ezcConsoleOption(
                'h',
                'help',
                \ezcConsoleInput::TYPE_NONE,
                NULL,
                FALSE,
                '',
                '',
                array(),
                array(),
                FALSE,
                FALSE,
                TRUE
               )
            );

            $input->registerOption(
              new \ezcConsoleOption(
                '',
                'names',
                \ezcConsoleInput::TYPE_STRING,
                '*.php',
                FALSE
               )
            );

            $input->registerOption(
              new \ezcConsoleOption(
                '',
                'quiet',
                \ezcConsoleInput::TYPE_NONE,
                NULL,
                FALSE
               )
            );

            $input->registerOption(
              new \ezcConsoleOption(
                'v',
                'version',
                \ezcConsoleInput::TYPE_NONE,
                NULL,
                FALSE,
                '',
                '',
                array(),
                array(),
                FALSE,
                FALSE,
                TRUE
               )
            );

            try {
                $input->process();
            }

            catch (\ezcConsoleOptionException $e) {
                print $e->getMessage() . "\n";
                exit(1);
            }

            if ($input->getOption('help')->value) {
                $this->showHelp();
                exit(0);
            }

            else if ($input->getOption('version')->value) {
                $this->printVersionString();
                exit(0);
            }

            $arguments = $input->getArguments();

            if (empty($arguments)) {
                $this->showHelp();
                exit(1);
            }

            $checkstyle  = $input->getOption('checkstyle')->value;
            $excludes    = $input->getOption('exclude')->value;
            $rulesetFile = $input->getOption('ruleset')->value;
            $names       = explode(',', $input->getOption('names')->value);
            $quiet       = $input->getOption('quiet')->value;

            array_map('trim', $names);

            $this->printVersionString();

            $finder = new FinderFacade($arguments, $excludes, $names);
            $files  = $finder->findFiles();

            if (!$rulesetFile) {
                $rulesetFile = $this->getDefaultRulesetFile();
            }

            try {
                $ruleset = new Ruleset($rulesetFile);
                $rules   = $ruleset->getRules();
            }

            catch (\Exception $e) {
                $this->showError('Could not read ruleset.');
            }

            printf("Using ruleset %s\n\n", realpath($rulesetFile));

            $analyzer = new Analyzer;
            $result   = new Result;
            $result->setRules($rules);

            try {
                $analyzer->run($files, $result);
            }
            catch (\RuntimeException $e) {
                $this->showError($e->getMessage());
            }

            if ($checkstyle) {
                $report = new Checkstyle;
                $report->generate($result, $checkstyle);
            }

            if (!$quiet) {
                $report = new Text;
                $report->generate($result, 'php://stdout');
            }

            $numFilesWithViolations = 0;
            $numViolations          = 0;

            foreach ($result->getViolations() as $lines) {
                $numFilesWithViolations++;

                foreach ($lines as $violations) {
                    $numViolations += count($violations);
                }
            }

            printf(
              "%sFound %d violation%s in %d file%s (out of %d total file%s).\n",
              !$quiet && $numViolations > 0 ? "\n" : '',
              $numViolations,
              $numViolations != 1 ? 's' : '',
              $numFilesWithViolations,
              $numFilesWithViolations != 1 ? 's' : '',
              count($files),
              count($files) != 1 ? 's' : ''
            );

            if ($numViolations > 0) {
                exit(1);
            }
        }

        /**
         * Shows an error.
         *
         * @param string $message
         * @since Method available since Release 1.0.4
         */
        protected function showError($message)
        {
            print $message . "\n";
            exit(1);
        }

        /**
         * Shows the help.
         */
        protected function showHelp()
        {
            $this->printVersionString();

            print <<<EOT
Usage: hphpa [switches] <directory|file> ...

  --checkstyle <file>     Write report in Checkstyle XML format to file.
  --ruleset <file>        Read list of rules to apply from XML file.

  --exclude <dir>         Exclude <dir> from code analysis.
  --names <names>         A comma-separated list of file names to check.
                          (default: *.php)

  --help                  Prints this usage information.
  --version               Prints the version and exits.

  --quiet                 Do not print violations.

EOT;
        }

        /**
         * Prints the version string.
         */
        protected function printVersionString()
        {
            printf(
              "hphpa %s by Sebastian Bergmann.\n\n", Version::id()
            );
        }

        /**
         * @return string
         * @since  Method available since Release 1.1.0
         */
        protected function getDefaultRulesetFile()
        {
            if (strpos('@data_dir@', '@data_dir') === 0) {
                return dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'ruleset.xml';
            }

            return'@data_dir@' . DIRECTORY_SEPARATOR . 'hphpa' . DIRECTORY_SEPARATOR . 'ruleset.xml';
        }
    }
}
