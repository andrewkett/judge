<?php
class Functions_Sniffs_AvoidableCalls_EvalSniff implements PHP_CodeSniffer_Sniff
{
    public function register()
    {
        return array(T_EVAL);
    }

    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
    }
}
