DROP FUNCTION IF EXISTS fn_engine_cut_status;

DELIMITER $$

CREATE FUNCTION fn_engine_cut_status(
    p_tracker_model VARCHAR(50),
    p_state VARCHAR(255),
    p_output_state VARCHAR(255)
)
RETURNS VARCHAR(1)
DETERMINISTIC
BEGIN

    RETURN CASE

        WHEN p_tracker_model LIKE 'Totem%'
             OR p_tracker_model = 'Totemtech'
        THEN
            fn_output_engine_cut(
                p_tracker_model,
                p_state
            )

        WHEN p_tracker_model IN (
            'FiFoTrack',
            'iStartek'
        )
        THEN
            fn_output_engine_cut(
                p_tracker_model,
                p_output_state
            )

        ELSE NULL

    END;

END$$

DELIMITER ;