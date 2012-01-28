<?php
/**
 * hphpa
 *
 * Copyright (c) 2012, Sebastian Bergmann <sb@sebastian-bergmann.de>.
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
 * @author    Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright 2012 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @since     File available since Release 1.0.0
 */

/**
 * Parser for HipHop CodeErrors.js logfile.
 *
 * @author    Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright 2012 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   Release: @package_version@
 * @link      http://github.com/sebastianbergmann/hphpa/tree
 * @since     Class available since Release 1.0.0
 */
class HPHPA_Result
{
    /**
     * @var array
     */
    protected $blacklist = array(
      'BadPHPIncludeFile' => TRUE,
      'PHPIncludeFileNotFound' => TRUE,
      'UnknownBaseClass' => TRUE,
      'UnknownClass' => TRUE,
      'UnknownFunction' => TRUE
    );

    /**
     * @var array
     */
    protected $typeToMessageMap = array(
      'BadPHPIncludeFile' => 'Bad include: %s',
      'PHPIncludeFileNotFound' => 'Include not found: %s',
      'UseEvaluation' => 'Usage of eval()',
      'UseUndeclaredVariable' => 'Variable "%s" is not declared',
      'UseUndeclaredGlobalVariable' => 'Global variable "%s" is not declared',
      'UseUndeclaredConstant' => 'Constant "%s" is not declared',
      'UnknownClass' => 'Class "%s" is unknown',
      'UnknownBaseClass' => 'Base class "%s" is unknown',
      'UnknownObjectMethod' => 'Method "%s" is unknown',
      'InvalidMagicMethod' => 'Magic method "%s" is invalid',
      'UnknownFunction' => 'Unknown function "%s"',
      'BadConstructorCall' => 'Bad call to constructor: %s',
      'DeclaredVariableTwice' => 'Variable is declared twice: %s',
      'DeclaredConstantTwice' => 'Constant is declared twice: %s',
      'BadDefine' => 'Bad define: %s',
      'RequiredAfterOptionalParam' => 'Required parameters after optional parameters: %s',
      'RedundantParameter' => 'Redundant parameter: %s',
      'TooFewArgument' => 'Too few arguments in function or method call: %s',
      'TooManyArgument' => 'Too many arguments in function or method call: %s',
      'BadArgumentType' => 'Bad argument type: %s',
      'StatementHasNoEffect' => 'Statement "%s" has no effect',
      'UseVoidReturn' => 'Usage of void return value from "%s"',
      'MissingObjectContext' => 'Trying to use $this in static context',
      'MoreThanOneDefault' => 'More than one default in switch statement',
      'InvalidArrayElement' => 'Invalid array element: %s',
      'InvalidDerivation' => 'Invalid inheritance: %s',
      'InvalidOverride' => 'Invalid override: %s',
      'ReassignThis' => 'Reassignment of $this',
      'MissingAbstractMethodImpl' => 'Implementation of abstract methods missing: %s',
      'BadPassByReference' => 'Bad pass-by-reference: %s',
      'ConditionalClassLoading' => 'Class "%s" is conditionally loaded',
      'GotoUndefLabel' => 'GOTO to invalid label "%s"',
      'GotoInvalidBlock' => 'GOTO to invalid block: %s',
      'AbstractProperty' => 'Attribute "%s" is abstract',
      'UnknownTrait' => 'Trait "%s" is unknown',
      'MethodInMultipleTraits' => 'Method "%s" is declared in multiple traits',
      'UnknownTraitMethod' => 'Trait method "%s" is unknown',
      'InvalidAccessModifier' => 'Access modified "%s" is invalid',
      'CyclicDependentTraits' => 'Cyclic dependency between traits: %s',
      'InvalidTraitStatement' => 'Invalid trait statement: %s',
      'RedeclaredTrait' => 'Trait "%s" is declared twice',
      'InvalidInstantiation' => 'Invalid instantiation: %s'
    );

    /**
     * @var array
     */
    protected $violations = array();

    /**
     * @param array $errors
     */
    public function __construct(array $errors)
    {
        $this->parse($errors);
    }

    /**
     * @return array
     */
    public function getViolations()
    {
        return $this->violations;
    }

    /**
     * @param array $codeErrors
     */
    protected function parse(array $errors)
    {
        foreach ($errors[1] as $rule => $violations) {
            if (isset($this->blacklist[$rule]) || !is_array($violations)) {
                continue;
            }

            foreach ($violations as $file) {
                $filename = $file['c1'][0];
                $line     = $file['c1'][1];
                $message  = trim($file['d']);

                if ($this->typeToMessageMap[$rule] != '') {
                    $message = sprintf(
                      $this->typeToMessageMap[$rule], $message
                    );
                } else {
                    $message = $rule . ': ' . $message;
                }

                if (!isset($this->violations[$filename])) {
                    $this->violations[$filename] = array();
                }

                if (!isset($this->violations[$filename][$line])) {
                    $this->violations[$filename] = array();
                }

                $this->violations[$filename][$line][] = array(
                  'message'  => $message,
                  'source'   => $rule
                );
            }
        }
    }
}