#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

const DICTIONARY_PATH = __DIR__ . '/dictionary.txt';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\SingleCommandApplication;

/**
 * Ask the user to enter a character. Validates the character is in the alphabet. Trims input > 1 character, taking
 * just the first character entered.
 *
 * @param InputInterface   $input
 * @param OutputInterface  $output
 * @param QuestionHelper   $helper
 *
 * @return string
 */
function getCharacter(InputInterface $input, OutputInterface $output, QuestionHelper $helper): string
{
    $question = new Question('Which character?: ');
    $character = $helper->ask($input, $output, $question);
    try {
        if (! $character) {
            throw new Exception('You must enter a single character [a-z].');
        }
        if (strlen($character) > 1) {
            $character = substr($character, 0, 1);
        }
        $character = strtolower($character);
        if (! in_array($character, range('a', 'z'))) {
            throw new Exception('You must enter a single character [a-z].');
        }
    } catch (Exception $e) {
        $output->writeln($e->getMessage());
        return getCharacter($input, $output, $helper);
    }
    return $character;
}

/**
 * Generate a list of possible words given the known valid characters, invalid characters, and correctly positioned
 * characters. Returns the list as an array.
 *
 * @param array  $dictionary
 * @param array  $knownValidChars
 * @param array  $knownInvalidChars
 * @param array  $correctPositions
 *
 * @return array
 */
function getListOfWords(array $dictionary, array $knownValidChars, array $knownInvalidChars, array $correctPositions): array
{
    $validWords = [];
    foreach ($dictionary as $word) {
        $wordArr = str_split(trim($word));
        foreach ($correctPositions as $k => $char) {
            if ($char && $char !== $wordArr[$k]) {
                continue 2;
            }
        }
        if (count(array_diff($knownValidChars, $wordArr)) > 0) {
            continue;
        }
        if(count(array_diff($wordArr, $knownInvalidChars)) !== 5) {
            continue;
        }
        $validWords[] = $word;
    }
    return $validWords;
}

/**
 * Fetch the user's menu option. Defaults to eXit.
 *
 * @param InputInterface   $input
 * @param OutputInterface  $output
 * @param QuestionHelper   $helper
 *
 * @return string
 */
function getMenuChoice(InputInterface $input, OutputInterface $output, QuestionHelper $helper): string
{
    $question = new Question('Please choose an action from the menu [x]: ', 'x');
    return $helper->ask($input, $output, $question);
}

/**
 * Load a text file (specified in DICTIONARY_PATH) into memory. These are the words that will be suggested. This script
 * does not validate the entries in the dictionary, it assumes you have already done that.
 *
 * @return array
 */
function loadDictionary(): array
{
    $dictionary = [];
    $handle = fopen(DICTIONARY_PATH, 'r');
    while (($line = fgets($handle)) !== false) {
        $dictionary[] = $line;
    }
    fclose($handle);
    return $dictionary;
}

/**
 * Draw the menu for the user
 *
 * @param OutputInterface  $output
 * @param array            $correctPositions
 * @param array            $knownValidChars
 * @param array            $knownInvalidChars
 */
function outputMenu(OutputInterface $output, array $correctPositions, array $knownValidChars, array $knownInvalidChars): void
{
    $output->writeln([
        'Wordle Helper Menu',
        '=========================================================',
        '[c] - Set a known, valid character in an unknown position',
        '[u] - Unset a known, valid character',
        '[i] - Set a known, invalid character',
        '[n] - Unset a known, invalid character',
        '[1-5] - Set a known character in a specific space',
        '[l] - List potential words',
        '[x] - Exit',
        '----------------------------------------------------------'
    ]);
    if ($knownInvalidChars) {
        $output->writeln('Invalid characters: ' . implode(', ', $knownInvalidChars));
    }
    if ($knownValidChars) {
        $output->writeln('Valid characters: ' . implode(', ', $knownValidChars));
    }
    $word = '';
    foreach ($correctPositions as $char) {
        $word .= (strlen($char) ? $char : '_') . ' ';
    }
    $output->writeln(['Word: ' . $word, '']);
}

(new SingleCommandApplication())
    ->setName('Wordle Helper') // Optional
    ->setVersion('1.0.0') // Optional
    ->addOption('dictionary', 'd', InputOption::VALUE_REQUIRED)
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $run = true;
        $correctPositions = ["", "", "", "", ""];
        $knownValidChars = [];
        $knownInvalidChars = [];
        $dictionary = loadDictionary();
        $helper = new QuestionHelper();
        while ($run) {
            outputMenu($output, $correctPositions, $knownValidChars, $knownInvalidChars);
            $option = getMenuChoice($input, $output, $helper);
            switch ($option) {
                default:
                case 'x':
                    $run = false;
                    break;

                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                    $correctPositions[intval($option) - 1] = getCharacter($input, $output, $helper);
                    break;

                case 'c':
                    $knownValidChars[] = getCharacter($input, $output, $helper);
                    break;

                case 'u':
                    $char = getCharacter($input, $output, $helper);
                    foreach ($knownValidChars as $k => $v) {
                        if ($v == $char) {
                            unset($knownValidChars[$k]);
                            break;
                        }
                    }
                    break;

                case 'i':
                    $knownInvalidChars[] = getCharacter($input, $output, $helper);
                    break;

                case 'n':
                    $char = getCharacter($input, $output, $helper);
                    foreach ($knownInvalidChars as $k => $v) {
                        if ($v == $char) {
                            unset($knownInvalidChars[$k]);
                            break;
                        }
                    }
                    break;

                case 'l':
                    $output->writeln(getListOfWords($dictionary, $knownValidChars, $knownInvalidChars, $correctPositions));
                    break;
            }
        }
        return Command::SUCCESS;
    })
    ->run();
