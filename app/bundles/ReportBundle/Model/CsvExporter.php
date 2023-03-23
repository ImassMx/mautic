<?php

namespace Mautic\ReportBundle\Model;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Templating\Helper\FormatterHelper;
use Mautic\ReportBundle\Crate\ReportDataResult;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class CsvExporter.
 */
class CsvExporter
{
    /**
     * @var FormatterHelper
     */
    protected $formatterHelper;

    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(FormatterHelper $formatterHelper, CoreParametersHelper $coreParametersHelper, TranslatorInterface $translator)
    {
        $this->formatterHelper      = $formatterHelper;
        $this->coreParametersHelper = $coreParametersHelper;
        $this->translator           = $translator;
    }

    /**
     * @param resource $handle
     * @param int      $page
     */
    public function export(ReportDataResult $reportDataResult, $handle, $page = 1)
    {
        if (1 === $page) {
            $this->putHeader($reportDataResult, $handle);
        }

        foreach ($reportDataResult->getData() as $data) {
            $row = [];
            foreach ($data as $k => $v) {
                $type       = $reportDataResult->getType($k);
                $typeString = 'string' !== $type;
                $row[]      = $typeString ? $this->formatterHelper->_($v, $type, true) : $v;
            }
            $this->putRow($handle, $row);
        }

        $totalsRow = $reportDataResult->getTotalsToExport();
        if (!empty($totalsRow) && $reportDataResult->isLastPage()) {
            $key = array_key_first($totalsRow);

            if (empty($totalsRow[$key])) {
                $totalsRow[$key] = $this->translator->trans('mautic.report.report.groupby.totals');
            }

            $this->putTotals($totalsRow, $handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return void
     */
    private function putHeader(ReportDataResult $reportDataResult, $handle)
    {
        $this->putRow($handle, $reportDataResult->getHeaders());
    }

    /**
     * @param array<string> $totals
     * @param resource      $handle
     *
     * @return void
     */
    private function putTotals(array $totals, $handle)
    {
        $this->putRow($handle, $totals);
    }

    /**
     * @param resource $handle
     */
    private function putRow($handle, array $row)
    {
        if ($this->coreParametersHelper->get('csv_always_enclose')) {
            fputs($handle, '"'.implode('","', $row).'"'."\n");
        } else {
            fputcsv($handle, $row);
        }
    }
}
