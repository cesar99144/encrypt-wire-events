<?php

namespace CesarFerreira\EncryptWireEvents;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Livewire\Drawer\Utils;
use Livewire\Mechanisms\HandleComponents\ViewContext;
use function Livewire\{store, trigger, wrap };
use ReflectionUnionType;
use Livewire\Mechanisms\Mechanism;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Exceptions\MethodNotFoundException;
use Illuminate\Support\Facades\View;

use Livewire\Mechanisms\HandleComponents\HandleComponents as BaseHandleComponents;
class CustomHandleComponents extends BaseHandleComponents
{
    public function update($snapshot, $updates, $calls)
    {
        $data = $snapshot['data'];
        $memo = $snapshot['memo'];

        $calls[0]['method'] = $this->decryptValue($calls[0]['method']);

        if (config('app.debug')) $start = microtime(true);
        [ $component, $context ] = $this->fromSnapshot($snapshot);

        $this->pushOntoComponentStack($component);

        trigger('hydrate', $component, $memo, $context);

        $this->updateProperties($component, $updates, $data, $context);
        if (config('app.debug')) trigger('profile', 'hydrate', $component->getId(), [$start, microtime(true)]);

        $this->callMethods($component, $calls, $context);

        if (config('app.debug')) $start = microtime(true);
        if ($html = $this->render($component)) {
            $context->addEffect('html', $html);
            if (config('app.debug')) trigger('profile', 'render', $component->getId(), [$start, microtime(true)]);
        }

        if (config('app.debug')) $start = microtime(true);
        trigger('dehydrate', $component, $context);

        $snapshot = $this->snapshot($component, $context);
        if (config('app.debug')) trigger('profile', 'dehydrate', $component->getId(), [$start, microtime(true)]);

        trigger('destroy', $component, $context);

        $this->popOffComponentStack();

        return [ $snapshot, $context->effects ];
    }

    protected function render($component, $default = null)
    {
        if ($html = store($component)->get('skipRender', false)) {
            $html = value(is_string($html) ? $html : $default);

            if (! $html) return;

            return Utils::insertAttributesIntoHtmlRoot($html, [
                'wire:id' => $component->getId(),
            ]);
        }

        [ $view, $properties ] = $this->getView($component);

        return $this->trackInRenderStack($component, function () use ($component, $view, $properties) {
            $finish = trigger('render', $component, $view, $properties);

            $revertA = Utils::shareWithViews('__livewire', $component);
            $revertB = Utils::shareWithViews('_instance', $component); // @deprecated

            $viewContext = new ViewContext;

            $html = $view->render(function ($view) use ($viewContext) {
                $viewContext->extractFromEnvironment($view->getFactory());
            });

            $revertA(); $revertB();

            $html = Utils::insertAttributesIntoHtmlRoot($html, [
                'wire:id' => $component->getId(),
            ]);

            $replaceHtml = function ($newHtml) use (&$html) {
                $html = $newHtml;
            };

            $html = $finish($html, $replaceHtml, $viewContext);

            $html = $this->prepareHtml($html);

            return $html;
        });
    }

    public function prepareHtml($html)
    {

        preg_match_all('/wire:(click|focus)=(["\'])(.*?)\2/', $html, $matches);

        foreach ($matches[3] as $originalValue) {

            $encryptedValue = $this->encryptValue($originalValue);

            $html = str_replace("wire:click=\"{$originalValue}\"", "wire:click=\"{$encryptedValue}\"", $html);
            $html = str_replace("wire:click='{$originalValue}'", "wire:click=\"{$encryptedValue}\"", $html);

            $html = str_replace("wire:focus=\"{$originalValue}\"", "wire:focus=\"{$encryptedValue}\"", $html);
            $html = str_replace("wire:focus='{$originalValue}'", "wire:focus=\"{$encryptedValue}\"", $html);
        }

        return $html;
    }

    public function encryptValue($value)
    {
        $encryptedValue = Crypt::encryptString($value);
        return $encryptedValue;
    }

    public function decryptValue($encryptedValue)
    {
        try {
            $decryptedValue = Crypt::decryptString($encryptedValue);
            return $decryptedValue;
        } catch (DecryptException $e) {
            return null;
        }
    }
}