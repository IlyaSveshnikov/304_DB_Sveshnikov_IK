import pandas as pd

df = pd.read_csv("ratings.csv")

min_id = df["userId"].min()
max_id = df["userId"].max()

min_count = (df["userId"] == min_id).sum()
max_count = (df["userId"] == max_id).sum()

with open("ratings_count.txt", "w") as f:
    f.write(f"{min_id} {min_count}\n")
    f.write(f"{max_id} {max_count}\n")