<?php
mb_internal_encoding("UTF-8");

 function autoload_db66cb82e8fe3f8dcb5e0586d945755b($class)
{
    $classes = array(
        'PatientDataType' => __DIR__ .'/PatientDataType.php'
        , 'FilledQuestionnaireType' => __DIR__ .'/FilledQuestionnaireType.php'
        , 'QuestionnaireAnswerType' => __DIR__ .'/QuestionnaireAnswerType.php'
        , 'MyTypeValidate' => __DIR__ .'/../MyTypeValidate.php'
        , 'MyTypes' => __DIR__ .'/../MyTypes.php'
        , 'MyExceptionCodes' => __DIR__ .'/../MyExceptionCodes.php'
        , 'MyStreamDBwork' => __DIR__ .'/../MyStreamDBwork.php'
        , 'MyStreamLib' => __DIR__ .'/../MyStreamLib.php'
        , 'MyLib' => __DIR__ .'/../MyLib.php'
        , 'ValidateException' => __DIR__ .'/../ValidateException.php'
        , 'WrongDataException' => __DIR__ .'/../WrongDataException.php'

        , 'class_InputDataTypes' => __DIR__ .'/class_InputDataTypes.php'
        , 'class_OutputDataTypes' => __DIR__ .'/class_OutputDataTypes.php'
        , 'SlotType' => __DIR__ . '/SlotType.php'
        , 'ExaminationStatuses' => __DIR__ . '/ExaminationStatuses.php'
        , 'IdentifyPatientResult' => __DIR__ .'/IdentifyPatientResult.php'
        , 'QuestioningResult' => __DIR__ .'/QuestioningResult.php'
        , 'GetAvailableSlotsResult' => __DIR__ .'/GetAvailableSlotsResult.php'
        , 'ValidationErrorType' => __DIR__ .'/ValidationErrorType.php'
        , 'ErrorType' => __DIR__ .'/ErrorType.php'
        , 'BookingResult' => __DIR__ .'/BookingResult.php'
        , 'BookingDataType' => __DIR__ .'/BookingDataType.php'
        , 'BookedServiceType' => __DIR__ .'/BookedServiceType.php'
        , 'MedicalServiceType' => __DIR__ .'/MedicalServiceType.php'
        , 'BookingResourceType' => __DIR__ .'/BookingResourceType.php'
        , 'ClinicType' => __DIR__ .'/ClinicType.php'
        , 'EmployeeType' => __DIR__ .'/EmployeeType.php'
        , 'ChosenServiceType' => __DIR__ .'/ChosenServiceType.php'
        , 'CancelBookingResult' => __DIR__ .'/CancelBookingResult.php'

    );
    if (!empty($classes[$class])) {
        include $classes[$class];
    };
}

spl_autoload_register('autoload_db66cb82e8fe3f8dcb5e0586d945755b');

// Do nothing. The rest is just leftovers from the code generation.
{
}

