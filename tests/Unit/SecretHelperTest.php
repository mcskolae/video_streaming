<?php

declare(strict_types=1);

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/secrets-'.bin2hex(random_bytes(4));
    mkdir($this->directory);
    putenv('SECRETS_PATH='.$this->directory);
});

afterEach(function () {
    putenv('SECRETS_PATH');
    putenv('SECRET_TEST_VALUE');
    array_map('unlink', glob($this->directory.'/*') ?: []);
    rmdir($this->directory);
});

it('reads the secret from the file named after the variable, in lowercase', function () {
    file_put_contents($this->directory.'/secret_test_value', "from-file\n");

    expect(secret('SECRET_TEST_VALUE'))->toBe('from-file');
});

it('prefers the secret file over the environment variable', function () {
    file_put_contents($this->directory.'/secret_test_value', 'from-file');
    putenv('SECRET_TEST_VALUE=from-env');

    expect(secret('SECRET_TEST_VALUE'))->toBe('from-file');
});

it('falls back to the environment variable when no file exists', function () {
    putenv('SECRET_TEST_VALUE=from-env');

    expect(secret('SECRET_TEST_VALUE'))->toBe('from-env');
});

it('returns the default when neither a file nor a variable exists', function () {
    expect(secret('SECRET_TEST_VALUE', 'fallback'))->toBe('fallback')
        ->and(secret('SECRET_TEST_VALUE'))->toBeNull();
});

it('ignores an empty secret file', function () {
    file_put_contents($this->directory.'/secret_test_value', "  \n");

    expect(secret('SECRET_TEST_VALUE', 'fallback'))->toBe('fallback');
});
