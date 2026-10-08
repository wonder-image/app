<?php

    function breadcrumb($list, $script = true) {

        $data = ['@type' => 'BreadcrumbList', 'itemListElement' => []];
        if ($script) $data = ['@context' => 'https://schema.org/'] + $data;
        foreach ($list as $url => $name) {
            $data['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => count($data['itemListElement']) + 1,
                'item' => ['@id' => (string) $url, 'name' => (string) $name],
            ];
        }
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE);

        return $script ? '<script type="application/ld+json">'.$json.'</script>' : $json;

    }
