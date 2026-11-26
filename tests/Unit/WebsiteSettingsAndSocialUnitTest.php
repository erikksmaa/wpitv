<?php

namespace Tests\Unit;

use App\Services\PublicSocialAccounts;
use App\Services\WebsiteSettings;
use App\Support\CoverageWhatsApp;
use PHPUnit\Framework\TestCase;

class WebsiteSettingsAndSocialUnitTest extends TestCase
{
    public function test_whatsapp_normalization_supports_indonesian_prefixes_and_rejects_invalid(): void
    {
        $wa = new CoverageWhatsApp;

        $this->assertSame('6281234567890', $wa->normalizePhone('081234567890'));
        $this->assertSame('6281234567890', $wa->normalizePhone('0812-3456-7890'));
        $this->assertSame('6281234567890', $wa->normalizePhone('6281234567890'));
        $this->assertSame('6281234567890', $wa->normalizePhone('+62 812 3456 7890'));
        $this->assertNull($wa->normalizePhone(''));
        $this->assertNull($wa->normalizePhone('abc'));
        $this->assertNull($wa->normalizePhone('07123456789'));
        $this->assertNull($wa->normalizePhone('12345'));
    }

    public function test_sql_script_contains_required_additive_statements_without_destructive_ddl(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2).'/database/sql/website_settings_and_social_integration.sql');

        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `wpitv_settings`', $sql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `wpitv_setting_events`', $sql);
        $this->assertStringContainsString('ADD COLUMN `show_on_website` TINYINT(1) NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString('ADD COLUMN `display_order` INT UNSIGNED NOT NULL DEFAULT 0', $sql);
        $this->assertStringNotContainsStringIgnoringCase('DROP TABLE', $sql);
        $this->assertStringNotContainsStringIgnoringCase('TRUNCATE', $sql);
    }

    public function test_service_constants_remain_well_formed(): void
    {
        $this->assertSame('coverage_whatsapp_number', WebsiteSettings::KEY_COVERAGE_WHATSAPP);
        $this->assertSame('contact_email', WebsiteSettings::KEY_CONTACT_EMAIL);
        $this->assertSame('contact_phone', WebsiteSettings::KEY_CONTACT_PHONE);
        $this->assertSame('contact_address', WebsiteSettings::KEY_CONTACT_ADDRESS);
        $this->assertNotSame(WebsiteSettings::CACHE_KEY, PublicSocialAccounts::CACHE_KEY);
    }

    public function test_official_contact_normalization_supports_office_phones_tel_links_and_whitespace_cleanup(): void
    {
        $settings = new WebsiteSettings;

        $this->assertSame('wpitv@stmik-wp.ac.id', $settings->normalizeEmail('  WPiTV@STMIK-WP.AC.ID  '));
        $this->assertNull($settings->normalizeEmail('   '));

        $this->assertSame('(0285) 427817', $settings->normalizeOfficePhone('  (0285)   427817  '));
        $this->assertSame('+62 285 427-817', $settings->normalizeOfficePhone(' +62  285   427-817 '));
        $this->assertSame('081234567890', $settings->normalizeOfficePhone('081234567890'));
        $this->assertNull($settings->normalizeOfficePhone('123'));
        $this->assertNull($settings->normalizeOfficePhone('abc-0285'));
        $this->assertNull($settings->normalizeOfficePhone(''));

        $this->assertSame('tel:0285427817', $settings->contactPhoneTelHref('(0285) 427817'));
        $this->assertSame('tel:+62285427817', $settings->contactPhoneTelHref('+62 285-427817'));
        $this->assertNull($settings->contactPhoneTelHref(''));

        $this->assertSame(
            "Jl. Patriot 25 Lt. 2, Pekalongan\nJawa Tengah, Indonesia",
            $settings->normalizeAddress("  Jl.   Patriot 25 Lt. 2, Pekalongan \r\n\r\n  Jawa Tengah,   Indonesia  ")
        );
        $this->assertNull($settings->normalizeAddress(" \n  \t "));
    }
}
