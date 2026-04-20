<?php

    $files = scanParentDir("$ROOT_APP/build/table/");

    foreach ($files as $file) {
        if (isset(pathinfo($file)['extension']) && pathinfo($file)['extension'] == 'php') {
            require_once "$ROOT_APP/build/table/$file";
        }
    }

    $files = scanParentDir("$ROOT/custom/build/table/");

    foreach ($files as $file) {
        if (isset(pathinfo($file)['extension']) && pathinfo($file)['extension'] == 'php') {
            require_once "$ROOT/custom/build/table/$file";
        }
    }

    foreach (Wonder\App\ModelRegistry::all() as $tableName => $modelClass) {

        $existingSchema = isset($TABLE->{$tableName}) && is_array($TABLE->{$tableName})
            ? $TABLE->{$tableName}
            : [];

        $TABLE->{$tableName} = array_replace_recursive(
            $existingSchema,
            $modelClass::rawTableSchema()
        );

    }

    foreach ($TABLE as $tableName => $tableSchema) {

        Wonder\App\Table::key($tableName)->setSchema($tableSchema);

    }

    foreach (Wonder\App\ResourceRegistry::classes() as $resourceClass) {

        Wonder\App\Table::key($resourceClass::prepareSchemaName())
            ->setSchema($resourceClass::prepareSchema());

    }
