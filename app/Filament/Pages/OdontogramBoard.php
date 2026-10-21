<?php

namespace App\Filament\Pages;

use App\Enum\PatientSex;
use App\Models\Patient;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class OdontogramBoard extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-o-odontology';

    protected static ?string $navigationLabel = 'Odontograma';

    protected static ?string $title = 'Odontograma';

    protected string $view = 'filament.pages.odontogram-board';

    public ?int $patientId = null;

    public ?int $practitionerId = null;

    public ?string $document_number = null;

    public ?string $birth_date = null;

    public ?string $sex = null;

    public ?string $phone = null;

    public ?string $email = null;

    public function mount(): void
    {
        $this->form->fill();
        $this->fillFormWithPatientData();
    }

    public function updatedPatientId(): void
    {
        $this->fillFormWithPatientData();
    }

    public function updatedPractitionerId(): void
    {
        $patient = $this->getSelectedPatient();

        if ($patient && $patient->odontogram) {
            $patient->odontogram->update(['practitioner_id' => $this->practitionerId]);
        }
    }

    private function fillFormWithPatientData(): void
    {
        $patient = $this->getSelectedPatient();
        $odontogram = $patient?->odontogram;

        $this->form->fill([
            'patientId' => $patient?->id,
            'practitionerId' => $odontogram?->practitioner_id,
            'document_number' => $patient?->document_number,
            'birth_date' => $patient?->birth_date?->format('Y-m-d'),
            'sex' => $patient?->sex?->value,
            'phone' => $patient?->phone,
            'email' => $patient?->email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->description('datos del paciente y odontograma')
                    ->columns(3)
                    ->schema([
                        Select::make('patientId')
                            ->label('Paciente')
                            ->placeholder('Seleccione un paciente')
                            ->default(fn (): ?int => Patient::query()
                                ->orderBy('first_name')
                                ->value('id'))
                            ->options(fn (): array => Patient::query()
                                ->orderBy('first_name')
                                ->get()
                                ->mapWithKeys(fn (Patient $patient): array => [
                                    $patient->id => "{$patient->fullName()} ",
                                ])
                                ->all())
                            ->live()
                            ->searchable(),
                        Select::make('practitionerId')
                            ->label('Odontólogo')
                            ->placeholder('Seleccione un odontólogo')
                            ->options(fn (): array => User::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [
                                    $user->id => $user->name,
                                ])
                                ->all())
                            ->searchable()
                            ->live(),
                        TextInput::make('document_number')
                            ->label('Número de documento')
                            ->disabled(),
                        DatePicker::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->native(false)
                            ->disabled(),
                        Select::make('sex')
                            ->label('Sexo')
                            ->options(PatientSex::class)
                            ->native(false)
                            ->disabled(),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->disabled(),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->disabled(),
                    ]),
            ]);
    }

    public function getSelectedPatient(): ?Patient
    {
        return $this->patientId ? Patient::find($this->patientId) : null;
    }
}
