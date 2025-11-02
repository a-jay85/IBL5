<?php

use PHPUnit\Framework\TestCase;
use Draft\DraftValidator;

class DraftValidatorTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new DraftValidator();
    }

    public function testValidateSucceedsWithValidSelection()
    {
        $result = $this->validator->validateDraftSelection('John Doe', null);
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateSucceedsWithEmptyStringCurrentSelection()
    {
        $result = $this->validator->validateDraftSelection('John Doe', '');
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateFailsWithNullPlayerName()
    {
        $result = $this->validator->validateDraftSelection(null, null);
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    public function testValidateFailsWithEmptyPlayerName()
    {
        $result = $this->validator->validateDraftSelection('', null);
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    public function testValidateFailsWhenPickAlreadyUsed()
    {
        $result = $this->validator->validateDraftSelection('John Doe', 'Jane Smith');
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("already drafted", $errors[0]);
    }

    public function testClearErrorsRemovesAllErrors()
    {
        $this->validator->validateDraftSelection(null, null);
        $this->assertNotEmpty($this->validator->getErrors());
        
        $this->validator->clearErrors();
        
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateResetsPreviousErrors()
    {
        // First validation should fail
        $this->validator->validateDraftSelection(null, null);
        $this->assertNotEmpty($this->validator->getErrors());
        
        // Second validation should succeed and clear previous errors
        $result = $this->validator->validateDraftSelection('John Doe', null);
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateFailsWhenPlayerAlreadyDrafted()
    {
        $result = $this->validator->validateDraftSelection('John Doe', null, true);
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("already been drafted by another team", $errors[0]);
    }

    public function testValidateSucceedsWhenPlayerNotAlreadyDrafted()
    {
        $result = $this->validator->validateDraftSelection('John Doe', null, false);
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateFailsWithPlayerAlreadyDraftedEvenIfPickNotUsed()
    {
        $result = $this->validator->validateDraftSelection('John Doe', '', true);
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("already been drafted by another team", $errors[0]);
    }
}
