<?php

namespace App\Livewire\Content;

use App\Models\ContentTemplate;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TemplateManager extends Component
{
    public string $siteId;

    // Form state
    public bool $showForm = false;
    public ?string $editingId = null;
    public string $name = '';
    public string $type = 'page';
    public string $htmlTemplate = '';
    public string $fieldsSchema = '';

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $template = ContentTemplate::findOrFail($id);
        $this->editingId = $id;
        $this->name = $template->name;
        $this->type = $template->type;
        $this->htmlTemplate = $template->html_template;
        $this->fieldsSchema = $template->fields_schema ? json_encode($template->fields_schema, JSON_PRETTY_PRINT) : '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|in:page,section,component',
            'htmlTemplate' => 'required|string',
        ]);

        $fieldsSchema = null;
        if ($this->fieldsSchema) {
            $fieldsSchema = json_decode($this->fieldsSchema, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('fieldsSchema', 'Invalid JSON');
                return;
            }
        }

        $data = [
            'site_id'       => $this->siteId,
            'name'          => $this->name,
            'type'          => $this->type,
            'html_template' => $this->htmlTemplate,
            'fields_schema' => $fieldsSchema,
        ];

        if ($this->editingId) {
            ContentTemplate::findOrFail($this->editingId)->update($data);
        } else {
            ContentTemplate::create($data);
        }

        $this->resetForm();
        session()->flash('success', 'Template saved.');
    }

    public function delete(string $id): void
    {
        ContentTemplate::findOrFail($id)->delete();
        session()->flash('success', 'Template deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->type = 'page';
        $this->htmlTemplate = '';
        $this->fieldsSchema = '';
    }

    public function render(): View
    {
        $templates = ContentTemplate::query()
            ->where(fn ($q) => $q->where('site_id', $this->siteId)->orWhereNull('site_id'))
            ->orderBy('name')
            ->get();

        return view('livewire.content.template-manager', [
            'templates' => $templates,
        ]);
    }
}
