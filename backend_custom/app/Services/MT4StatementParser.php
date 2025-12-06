<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;

class MT4StatementParser
{
    /**
     * Parse MT4/MT5 HTML statement file.
     */
    public function parseHTML(string $content): array
    {
        $trades = [];
        
        // Load HTML content
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($content);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        
        foreach ($tables as $table) {
            $rows = $table->getElementsByTagName('tr');
            $inTradesSection = false;
            
            foreach ($rows as $row) {
                $cells = $row->getElementsByTagName('td');
                $text = trim($row->textContent);
                
                // Detect trades section
                if (str_contains(strtolower($text), 'closed transactions') || 
                    str_contains(strtolower($text), 'closed trades')) {
                    $inTradesSection = true;
                    continue;
                }

                if (!$inTradesSection || $cells->length < 8) {
                    continue;
                }

                // Skip header rows
                $firstCell = trim($cells->item(0)->textContent);
                if (!is_numeric($firstCell)) {
                    continue;
                }

                try {
                    $trade = $this->parseTradeRow($cells);
                    if ($trade) {
                        $trades[] = $trade;
                    }
                } catch (Exception $e) {
                    // Skip invalid rows
                    continue;
                }
            }
        }

        return $trades;
    }

    /**
     * Parse a single trade row from HTML.
     */
    protected function parseTradeRow(\DOMNodeList $cells): ?array
    {
        $cellValues = [];
        foreach ($cells as $cell) {
            $cellValues[] = trim($cell->textContent);
        }

        // MT4 typical format: Ticket, Open Time, Type, Size, Item, Price, S/L, T/P, Close Time, Price, Commission, Taxes, Swap, Profit
        if (count($cellValues) < 12) {
            return null;
        }

        $ticket = (int) $cellValues[0];
        $openTime = $this->parseDateTime($cellValues[1]);
        $type = strtolower($cellValues[2]);
        $lots = (float) $cellValues[3];
        $pair = strtoupper($cellValues[4]);
        $openPrice = (float) $cellValues[5];
        $closeTime = $this->parseDateTime($cellValues[8]);
        $closePrice = (float) $cellValues[9];
        $commission = (float) str_replace([' ', ','], ['', '.'], $cellValues[10] ?? '0');
        $swap = (float) str_replace([' ', ','], ['', '.'], $cellValues[12] ?? '0');
        $profit = (float) str_replace([' ', ','], ['', '.'], $cellValues[13] ?? '0');

        // Skip balance/deposit entries
        if (in_array($type, ['balance', 'credit', 'deposit', 'withdrawal'])) {
            return null;
        }

        return [
            'ticket' => $ticket,
            'pair' => $pair,
            'type' => $this->normalizeType($type),
            'status' => $closeTime ? 'closed' : 'open',
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'open_price' => $openPrice,
            'close_price' => $closePrice,
            'lots' => $lots,
            'profit' => $profit,
            'swap' => $swap,
            'commission' => $commission,
        ];
    }

    /**
     * Parse CSV statement file.
     */
    public function parseCSV(string $content, string $delimiter = ','): array
    {
        $trades = [];
        $lines = explode("\n", $content);
        $headers = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line, $delimiter);
            
            // First row with expected columns is header
            if (!$headers && $this->isHeaderRow($row)) {
                $headers = $this->normalizeHeaders($row);
                continue;
            }

            if (!$headers) {
                continue;
            }

            try {
                $trade = $this->parseCSVRow($row, $headers);
                if ($trade) {
                    $trades[] = $trade;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $trades;
    }

    /**
     * Check if row is a header row.
     */
    protected function isHeaderRow(array $row): bool
    {
        $headerKeywords = ['ticket', 'order', 'symbol', 'pair', 'type', 'lot', 'profit'];
        $text = strtolower(implode(' ', $row));
        
        foreach ($headerKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Normalize header names.
     */
    protected function normalizeHeaders(array $headers): array
    {
        $mapping = [
            'ticket' => ['ticket', 'order', 'order#', 'ordernumber'],
            'pair' => ['symbol', 'pair', 'item', 'instrument'],
            'type' => ['type', 'action', 'direction', 'side'],
            'lots' => ['lots', 'volume', 'size', 'quantity'],
            'open_time' => ['opentime', 'open_time', 'entry_time', 'entrytime', 'open'],
            'close_time' => ['closetime', 'close_time', 'exit_time', 'exittime', 'close'],
            'open_price' => ['openprice', 'open_price', 'entry_price', 'entryprice'],
            'close_price' => ['closeprice', 'close_price', 'exit_price', 'exitprice'],
            'profit' => ['profit', 'pnl', 'p/l', 'result'],
            'swap' => ['swap', 'rollover'],
            'commission' => ['commission', 'comm', 'fee'],
        ];

        $normalized = [];
        foreach ($headers as $index => $header) {
            $headerLower = strtolower(preg_replace('/[^a-z0-9]/i', '', $header));
            
            foreach ($mapping as $standard => $variations) {
                foreach ($variations as $variation) {
                    if ($headerLower === $variation || str_contains($headerLower, $variation)) {
                        $normalized[$standard] = $index;
                        break 2;
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Parse a CSV row using normalized headers.
     */
    protected function parseCSVRow(array $row, array $headers): ?array
    {
        $ticket = isset($headers['ticket']) ? (int) $row[$headers['ticket']] : null;
        
        if (!$ticket) {
            return null;
        }

        $type = isset($headers['type']) ? strtolower($row[$headers['type']]) : 'buy';
        
        // Skip non-trade entries
        if (in_array($type, ['balance', 'credit', 'deposit', 'withdrawal'])) {
            return null;
        }

        return [
            'ticket' => $ticket,
            'pair' => isset($headers['pair']) ? strtoupper($row[$headers['pair']]) : 'UNKNOWN',
            'type' => $this->normalizeType($type),
            'status' => 'closed',
            'open_time' => isset($headers['open_time']) ? $this->parseDateTime($row[$headers['open_time']]) : now(),
            'close_time' => isset($headers['close_time']) ? $this->parseDateTime($row[$headers['close_time']]) : now(),
            'open_price' => isset($headers['open_price']) ? (float) $row[$headers['open_price']] : 0,
            'close_price' => isset($headers['close_price']) ? (float) $row[$headers['close_price']] : 0,
            'lots' => isset($headers['lots']) ? (float) $row[$headers['lots']] : 0.01,
            'profit' => isset($headers['profit']) ? (float) str_replace([' ', ','], ['', '.'], $row[$headers['profit']]) : 0,
            'swap' => isset($headers['swap']) ? (float) str_replace([' ', ','], ['', '.'], $row[$headers['swap']]) : 0,
            'commission' => isset($headers['commission']) ? (float) str_replace([' ', ','], ['', '.'], $row[$headers['commission']]) : 0,
        ];
    }

    /**
     * Parse datetime string.
     */
    protected function parseDateTime(string $datetime): ?Carbon
    {
        if (empty($datetime)) {
            return null;
        }

        $formats = [
            'Y.m.d H:i:s',
            'Y.m.d H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, trim($datetime));
            } catch (Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($datetime);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Normalize trade type.
     */
    protected function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        
        return match(true) {
            str_contains($type, 'buy') && str_contains($type, 'limit') => 'buy_limit',
            str_contains($type, 'buy') && str_contains($type, 'stop') => 'buy_stop',
            str_contains($type, 'sell') && str_contains($type, 'limit') => 'sell_limit',
            str_contains($type, 'sell') && str_contains($type, 'stop') => 'sell_stop',
            str_contains($type, 'buy') => 'buy',
            str_contains($type, 'sell') => 'sell',
            default => 'buy',
        };
    }
}

