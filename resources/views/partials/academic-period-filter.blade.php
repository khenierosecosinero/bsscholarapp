@if(!empty($academicYearOptions) && isset($academicPeriod))
    <form method="POST" action="{{ route('academic.period.update') }}" class="academic-period-filter">
        @csrf
        @method('PUT')
        <label>
            <span>Academic Year</span>
            <select name="year_start" aria-label="Academic year" onchange="this.form.submit()">
                @foreach($academicYearOptions as $year => $label)
                    <option value="{{ $year }}" @selected((int) $academicPeriod->year_start === (int) $year)>AY {{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Semester</span>
            <select name="semester" aria-label="Semester" onchange="this.form.submit()">
                @foreach($semesterOptions ?? ['1st Semester', '2nd Semester'] as $option)
                    <option value="{{ $option }}" @selected($academicPeriod->semester === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
    </form>
@endif
