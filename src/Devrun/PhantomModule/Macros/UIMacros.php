<?php
/**
 * This file is part of devrun.
 * Copyright (c) 2020
 *
 * @file    UICmsMacro.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\PhantomModule\Macros;

use Devrun\CmsModule\Utils\Common;
use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;

class UIMacros extends MacroSet
{

    /** @var Compiler */
    private $compiler;


    public static function install(Compiler $compiler)
    {
        $set = new static($compiler);
        $set->compiler = $compiler;

        $set->addMacro('phantomImg', [$set, 'tagImg'], NULL, [$set, 'attrImg']);
    }



    public function tagImg(MacroNode $node, PhpWriter $writer)
    {
        return $writer->write('$_img = $_imgStorage->fromIdentifier(%node.array); echo "<img src=\"" . $basePath . "/" . $_img->createLink() . "\">";');
    }




    public function attrImg(MacroNode $node, PhpWriter $writer)
    {
        $return = $writer->write('$_img = $_imgStorage->fromIdentifier(array_merge([%node.word->identifier], %node.array));');
        $node->tokenizer->reset();

        $return .= ' echo \'src="\' . $basePath . "/" . $_img->createLink() . \'"\';';
        $return .= 'if (!PhantomModule\Repositories\PhantomRepository::exist(%node.word)) { echo \'data-capture-route="\' . %node.word->route->id . \'"\' . \' data-capture-params="\' . htmlspecialchars(json_encode(%node.array)) . \'"\'; }';

        return $writer->write($return);
    }






    /**
     * @return bool is request from admin page
     */
    public static function isAdminRequest()
    {
        return Common::isAdminRequest();
    }

}