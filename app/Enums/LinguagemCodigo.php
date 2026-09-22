<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

/**
 * Linguagens aceitas nos blocos de código do enunciado.
 *
 * O valor é a classe que o realce de sintaxe usa (`language-…`), então
 * ele precisa bater com o nome que o highlight.js conhece.
 */
enum LinguagemCodigo: string implements Rotulavel
{
    use DescreveOpcoes;

    case Python = 'python';
    case JavaScript = 'javascript';
    case TypeScript = 'typescript';
    case Jsx = 'jsx';
    case Html = 'html';
    case Css = 'css';
    case Kotlin = 'kotlin';
    case Swift = 'swift';
    case Java = 'java';
    case CSharp = 'csharp';
    case C = 'c';
    case Cpp = 'cpp';
    case Php = 'php';
    case Sql = 'sql';
    case Bash = 'bash';
    case Json = 'json';
    case Texto = 'plaintext';

    public function rotulo(): string
    {
        return match ($this) {
            self::Python => 'Python',
            self::JavaScript => 'JavaScript',
            self::TypeScript => 'TypeScript',
            self::Jsx => 'React (JSX)',
            self::Html => 'HTML',
            self::Css => 'CSS',
            self::Kotlin => 'Kotlin',
            self::Swift => 'Swift',
            self::Java => 'Java',
            self::CSharp => 'C#',
            self::C => 'C',
            self::Cpp => 'C++',
            self::Php => 'PHP',
            self::Sql => 'SQL',
            self::Bash => 'Shell / Bash',
            self::Json => 'JSON',
            self::Texto => 'Texto sem realce',
        };
    }

    public function cor(): string
    {
        return 'cinza';
    }

    /** Classe usada pelo realce de sintaxe no HTML e no PDF. */
    public function classe(): string
    {
        return 'language-'.$this->value;
    }
}
