<?php

declare(strict_types=1);

use Wonder\Console\Commands\Config;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

final class ConfigRecommendedSkillsProbe extends Config
{
    public function recommendedSkills(): array
    {
        return self::NPX_SKILL;
    }
}

$skills = (new ConfigRecommendedSkillsProbe())->recommendedSkills();

check('Wonder raccomanda solo la propria raccolta di skill', function () use ($skills): bool {
    return $skills === [
        'wonder-image/skills' => "npx skills add wonder-image/skills --skill '*' --agent claude-code -y",
    ];
});

summary();
