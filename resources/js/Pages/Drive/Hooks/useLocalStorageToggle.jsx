import { useLocalStorageBool } from "./useLocalStorageBool";

export function useLocalStorageToggle(key) {
    const [value, setValue] = useLocalStorageBool(key);
    const toggle = () => setValue((v) => !v);
    return [value, toggle];
}
