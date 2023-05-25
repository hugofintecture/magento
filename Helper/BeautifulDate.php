<?php

declare(strict_types=1);

namespace Fintecture\Payment\Helper;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class BeautifulDate
{
    /** @var TimezoneInterface */
    protected $timezone;

    /** @var ResolverInterface */
    protected $localeResolver;

    public function __construct(
        TimezoneInterface $timezone,
        ResolverInterface $localeResolver
    ) {
        $this->timezone = $timezone;
        $this->localeResolver = $localeResolver;
    }

    public function formatDatetime(\DateTime $datetime): string
    {
        $locale = $this->localeResolver->getLocale();
        $timezone = $this->timezone->getConfigTimezone();

        $formatter = \IntlDateFormatter::create(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            $timezone
        );

        if ($formatter) {
            $formattedDatetime = $formatter->format($datetime);
            if ($formattedDatetime) {
                return $formattedDatetime;
            }
        }

        return '';
    }
}
