import { ScissorsIcon } from "lucide-react";
import Button from "./Generic/Button.jsx";

const CutButton = ({ classes, onCut }) => {
    return (
        <Button
            size="selected"
            classes={`border border-orange-900 text-orange-200 hover:bg-orange-950 active:bg-orange-900 ${classes}`}
            onClick={onCut}
            aria-label="Cut selected files"
            title="Cut selected files"
        >
            <ScissorsIcon className={`text-orange-500  w-4 h-4`} />
            {!classes && <span className={`hidden lg:inline`}>Cut</span>}
        </Button>
    );
};

export default CutButton;
