<?php

namespace LukasJankowski\StringArguments\Tests;

use InvalidArgumentException;
use LukasJankowski\StringArguments\ArgumentFactory;
use PHPUnit\Framework\TestCase;

class ArgumentFactoryTest extends TestCase
{
    public function provideArguments(): array
    {
        return [
            // Strings
            ['', ''],
            ['s:string', 'string'],
            ['s:', ''],
            // Integers
            ['i:', '_throws'],
            ['i:0', 0],
            ['i:123456', 123456],
            ['i:-999', -999],
            // Floats
            ['f:', '_throws'],
            ['f:0', 0.0],
            ['f:1.0001', 1.0001],
            ['f:1.2345', 1.2345],
            ['f:999.99', 999.99],
            ['f:-111111.111111', -111111.111111],
            // Null
            ['n:', null],
            ['n:null', null],
            // Booleans
            ['b:', '_throws'],
            ['b:true', true],
            ['b:false', false],
            // Arrays
            ['a:', []],
            ['a:[s:value,s:value,s:value]', ['value', 'value', 'value']],
            ['a:[s:value,i:123,f:1.23]', ['value', 123, 1.23]],
            ['a:[key=s:value,123=s:bar]', ['key' => 'value', 123 => 'bar']],
            ['a:[key=s:value,s:bar]', ['key' => 'value', 'bar']],
            ['a:[a:[s:value,s:value],s:value]', [['value', 'value'], 'value']],
            // Objects
            ['o:', []],
            ['o:[s:value,s:value,s:value]', ['value', 'value', 'value']],
            ['o:[s:value,i:123,f:1.23]', ['value', 123, 1.23]],
            ['o:[key=s:value,123=s:bar]', ['key' => 'value', 123 => 'bar']],
            ['o:[key=s:value,s:bar]', ['key' => 'value', 'bar']],
            ['o:[o:[s:value,s:value],s:value]', [['value', 'value'], 'value']],
            // Resource -> wont work because could be stream, curl, unknown, mysql_handle etc.
            // Class -> wont work because no Autowiring for potentially loaded constructors
            // Property -> wont work because no class reference is provided
            // Method -> wont work because no class reference is provided
        ];
    }

    /**
     * @dataProvider provideArguments
     */
    public function test_it_can_construct_arguments($actual, $expected)
    {
        if ($expected === '_throws') {
            $this->expectException(InvalidArgumentException::class);

            ArgumentFactory::make($actual);

            return;
        }

        if (str_starts_with($actual, 'o:')) {
            $expected = (object) array_map(fn ($val) => is_array($val) ? (object) $val : $val, $expected);
        }


        $this->assertEquals(
            $expected,
            ArgumentFactory::make($actual)
        );
    }

    public function test_it_throws_an_exception_if_a_type_requiring_a_value_doesnt_have_one()
    {
        $this->expectExceptionMessage('Type "integer" must have a value.');

        ArgumentFactory::make('i:');
    }

    public function test_it_can_use_a_different_compound_separator()
    {
        ArgumentFactory::setCompoundSeparator('@@');

        $this->assertEquals('@@', ArgumentFactory::getCompoundSeparator());
        $this->assertEquals(['value', 'value'], ArgumentFactory::make('a:[s:value@@s:value]'));
    }

    public function test_it_can_use_a_different_compound_assign()
    {
        ArgumentFactory::setCompoundAssign('=>');

        $this->assertEquals('=>', ArgumentFactory::getCompoundAssign());
        $this->assertEquals(['key' => 'value'], ArgumentFactory::make('a:[key=>s:value]'));
    }

    public function test_it_can_use_a_different_type_separator()
    {
        ArgumentFactory::setTypeSeparator('>>');

        $this->assertEquals('>>', ArgumentFactory::getTypeSeparator());
        $this->assertEquals(123, ArgumentFactory::make('i>>123'));
    }

    public function test_it_throws_an_exception_when_providing_invalid_values()
    {
        $this->expectExceptionMessage('Unable to cast "not-a-bool" to "bool"');

        ArgumentFactory::make('b:not-a-bool');
    }

    public function test_it_throws_an_exception_when_providing_an_invalid_type()
    {
        $this->expectExceptionMessage('Invalid argument type provided "r". Must be one of: s,i,f,b,n,a,o');

        ArgumentFactory::make('r:some-value');
    }
}
