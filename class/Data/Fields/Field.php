<?php

    namespace Wonder\Data\Fields;

    use Wonder\Concerns\HasSchema;
    use Wonder\Data\Support\ValidationResult;
    use Wonder\Data\Formatters\Formatter;
    use Wonder\Data\Validators\{Validator, RequiredValidator};

    abstract class Field {

        use HasSchema;

        public string $key;
        public string $type = 'text';

        public function __construct($key) 
        {

            $this->key = $key;

        }

        public static function key( string $key ): static
        {

            return new static($key);

        }

        public function required(): static
        {

            return $this->addValidator(new RequiredValidator());

        }

        public function isRequired(): bool
        {

            return $this->hasValidator(new RequiredValidator());

        }

        public function addFormatter( object $format ): static
        {

            if (!is_subclass_of($format, Formatter::class)) {
                throw new \Exception(
                    "La classe ".$format::class." non è un Formatter valido."
                );
            }
            
            return $this->schemaPush('formatters', $format);

        }


        public function addValidator( object $validator ): static
        {

            if (!is_subclass_of($validator, Validator::class)) {
                throw new \Exception(
                    "La classe ". $validator::class." non è un Validator valido."
                );
            }
            
            return $this->schemaPush('validators', $validator);

        }

        public function hasValidator( object $validator ): bool
        {

            if (is_array($this->getSchema('validators'))) {

                foreach ($this->getSchema('validators') as $registeredValidator) {
                    if ($registeredValidator::class === $validator::class) {
                        return true;
                    }
                }

            }

            return false;

        }

        public function formatters( array $formatters ): static
        {

            foreach ($formatters as $formatter) { $this->addFormatter($formatter); }

            return $this;

        }

        public function validators( array $validators ): static
        {

            foreach ($validators as $validator) { $this->addValidator($validator); }

            return $this;

        }

        public function validate( $value, array $input = [] ): ValidationResult 
        {

            if (is_array($this->getSchema('validators'))) {
                    
                foreach ($this->getSchema('validators') as $validator) {

                    $result = $validator->validate($value, $input);

                    if (!$result->isValid()) { return $result; }

                }

            }
            
            return ValidationResult::success($value);

        }


        public function format( $value )
        {

            if (is_array($this->getSchema('formatters'))) {

                foreach ($this->getSchema('formatters') as $formatter) {

                    $value = $formatter::format($value);

                }
                
            }

            return $value;

        }

    }
